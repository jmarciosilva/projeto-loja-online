<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductShippingProfile;
use App\Services\Shipping\ShippingMeasurementConverter;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductService
{
    private const MONEY_FORMAT = '/^\d{1,10}(?:[,.]\d{1,2})?$/';

    public function __construct(private readonly MediaService $mediaService) {}

    public function paginateAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()->with(['category', 'mainMedia', 'images'])->ordered()->paginate($perPage);
    }

    public function adminMediaOptions(): Collection
    {
        return $this->mediaService->availableForSelection();
    }

    public function paginatePublic(?string $categorySlug = null, int $perPage = 12): LengthAwarePaginator
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return new Paginator([], 0, $perPage, Paginator::resolveCurrentPage(), [
                'path' => Paginator::resolveCurrentPath(),
            ]);
        }

        return Product::query()->publiclyVisible()
            ->when($categorySlug !== null, fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $categorySlug)))
            ->with(['category', 'mainMedia', 'images'])
            ->ordered()
            ->paginate($perPage);
    }

    public function featured(int $limit = 4)
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return new Collection;
        }

        return Product::query()->publiclyVisible()->featured()
            ->with(['category', 'mainMedia', 'images'])
            ->ordered()->limit($limit)->get();
    }

    public function findPublicBySlug(string $slug): ?Product
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return null;
        }

        return Product::query()->publiclyVisible()->with(['category', 'mainMedia', 'images'])
            ->where('slug', $slug)->first();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product
    {
        $payload = $this->validatedPayload($attributes);
        $payload['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $payload['name']);
        $payload['sort_order'] = isset($attributes['sort_order']) ? (int) $attributes['sort_order'] : $this->nextOrder();

        return DB::transaction(function () use ($payload, $attributes): Product {
            if (filled($payload['sku'] ?? null)) {
                $product = Product::query()->create($payload);
                $this->syncGallery($product, $attributes, $payload['main_media_id']);
                $this->syncShippingProfile($product, $attributes);

                return $product;
            }

            // O id AUTO_INCREMENT do banco fornece uma sequência concorrente
            // segura. O SKU temporário só existe dentro da transação.
            $payload['sku'] = 'PENDING-'.Str::upper((string) Str::ulid());
            $product = Product::query()->create($payload);
            $candidate = $this->generatedSku($product->id);

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $product->sku = $candidate;
                try {
                    $product->save();

                    $this->syncGallery($product, $attributes, $payload['main_media_id']);
                    $this->syncShippingProfile($product, $attributes);

                    return $product;
                } catch (QueryException $exception) {
                    if (! $this->isUniqueViolation($exception)) {
                        throw $exception;
                    }
                    $candidate = $this->nextAvailableGeneratedSku($product->id, $candidate);
                }
            }

            throw new InvalidArgumentException('Não foi possível reservar um SKU automático. Tente novamente.');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product
    {
        $payload = $this->validatedPayload($attributes, $product);
        $payload['sku'] = $product->sku;
        if (array_key_exists('slug', $attributes) && filled($attributes['slug'])) {
            $payload['slug'] = $this->resolveSlug($attributes['slug'], $payload['name'] ?? $product->name, $product->id);
        }
        DB::transaction(function () use ($product, $payload, $attributes): void {
            $product->fill($payload)->save();
            $this->syncGallery($product, $attributes, $payload['main_media_id']);
            $this->syncShippingProfile($product, $attributes);
        });

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function slugIsAvailable(string $slug, ?int $ignoreId = null): bool
    {
        return ! Product::withTrashed()->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function skuIsAvailable(string $sku, ?int $ignoreId = null): bool
    {
        return ! Product::withTrashed()->where('sku', $sku)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public static function formatPrice(string $amount): string
    {
        [$integer, $decimal] = array_pad(explode('.', $amount, 2), 2, '00');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer) ?? $integer;

        return 'R$ '.$integer.','.str_pad($decimal, 2, '0');
    }

    public static function suggestedPrice(string $cost, string $marginPercent): string
    {
        $costCents = self::decimalCents($cost);
        $marginBasisPoints = self::percentageBasisPoints($marginPercent);
        if ($marginBasisPoints >= 10000) {
            throw new InvalidArgumentException('A margem precisa ser menor que 100%.');
        }

        $suggestedCents = self::divideRoundHalfUp($costCents * 10000, 10000 - $marginBasisPoints);
        if ($suggestedCents > 999999999999) {
            throw new InvalidArgumentException('O preço sugerido excede o limite de venda permitido.');
        }

        return self::formatCents($suggestedCents);
    }

    public static function suggestedPromotionPrice(string $price, string $discountPercent): string
    {
        $priceCents = self::decimalCents($price);
        $discountBasisPoints = self::percentageBasisPoints($discountPercent);
        if ($discountBasisPoints > 10000) {
            throw new InvalidArgumentException('O desconto não pode ultrapassar 100%.');
        }

        return self::formatCents(self::divideRoundHalfUp($priceCents * (10000 - $discountBasisPoints), 10000));
    }

    public static function effectiveMargin(string $cost, string $price): string
    {
        $costCents = self::decimalCents($cost);
        $priceCents = self::decimalCents($price);
        if ($priceCents === 0) {
            return '0.00';
        }

        return self::formatPercent(self::divideRoundHalfUp(($priceCents - $costCents) * 10000, $priceCents));
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function validatedPayload(array $attributes, ?Product $product = null): array
    {
        $categoryId = $attributes['category_id'] ?? $product?->category_id;
        $category = is_numeric($categoryId) ? Category::query()->find((int) $categoryId) : null;
        if ($category === null) {
            throw new InvalidArgumentException('Selecione uma categoria existente.');
        }
        if ($this->asBoolean($attributes['is_active'] ?? $product?->is_active ?? false)
            && ! $category->is_active) {
            throw new InvalidArgumentException('Ative a categoria antes de publicar o produto.');
        }

        $name = array_key_exists('name', $attributes) ? trim((string) $attributes['name']) : $product?->name;
        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException('O nome do produto é obrigatório.');
        }
        $skuInput = array_key_exists('sku', $attributes) && filled($attributes['sku'])
            ? strtoupper(trim((string) $attributes['sku']))
            : null;
        $sku = $product?->sku ?? $skuInput;
        if ($skuInput !== null && ! $this->skuIsAvailable($skuInput, $product?->id)) {
            throw new InvalidArgumentException('Este SKU já está em uso.');
        }

        $price = $this->money($attributes['price'] ?? $product?->price);
        $promotionalPrice = array_key_exists('promotional_price', $attributes)
            ? $attributes['promotional_price']
            : $product?->promotional_price;
        $promotionalPrice = $promotionalPrice === null || $promotionalPrice === '' ? null : $this->money($promotionalPrice);
        if ($promotionalPrice !== null && $this->cents($promotionalPrice) >= $this->cents($price)) {
            throw new InvalidArgumentException('O preço promocional deve ser menor que o preço normal.');
        }

        $costPrice = array_key_exists('cost_price', $attributes)
            ? $attributes['cost_price']
            : $product?->cost_price;
        $costPrice = $costPrice === null || $costPrice === '' ? null : $this->money($costPrice);
        $targetMargin = array_key_exists('target_margin', $attributes)
            ? $attributes['target_margin']
            : $product?->target_margin;
        $targetMargin = $targetMargin === null || $targetMargin === '' ? null : $this->percentage($targetMargin);
        if ($targetMargin !== null && $costPrice === null) {
            throw new InvalidArgumentException('Informe o preço de custo para usar uma margem desejada.');
        }
        if ($targetMargin !== null && self::percentageBasisPoints($targetMargin) >= 10000) {
            throw new InvalidArgumentException('A margem precisa ser menor que 100%.');
        }

        $promotionEnabled = $this->asBoolean($attributes['promotion_enabled'] ?? $product?->promotion_enabled ?? false);
        if ($promotionEnabled && $promotionalPrice === null) {
            throw new InvalidArgumentException('Informe o preço promocional para habilitar a promoção.');
        }
        $promotionStartsAt = $this->dateTime($attributes['promotion_starts_at'] ?? $product?->promotion_starts_at);
        $promotionEndsAt = $this->dateTime($attributes['promotion_ends_at'] ?? $product?->promotion_ends_at);
        if ($promotionStartsAt !== null && $promotionEndsAt !== null
            && Carbon::parse($promotionEndsAt, config('app.timezone'))->lessThanOrEqualTo(Carbon::parse($promotionStartsAt, config('app.timezone')))) {
            throw new InvalidArgumentException('O término da promoção deve ser posterior ao início.');
        }

        $mediaId = array_key_exists('main_media_id', $attributes) ? $attributes['main_media_id'] : $product?->main_media_id;
        if ($mediaId === '' || $mediaId === 0 || $mediaId === '0') {
            $mediaId = null;
        }
        if ($mediaId !== null) {
            $media = is_numeric($mediaId) ? Media::query()->find((int) $mediaId) : null;
            if ($media === null || $media->disk !== MediaService::DISK
                || ! in_array($media->mime_type, $this->mediaService->supportedMimeTypes(), true)
                || ! Storage::disk($media->disk)->exists($media->path)) {
                throw new InvalidArgumentException('A imagem selecionada não está disponível na biblioteca.');
            }
            $mediaId = $media->id;
        }

        $description = array_key_exists('description', $attributes) ? $attributes['description'] : $product?->description;
        $payload = [
            'category_id' => $category->id,
            'main_media_id' => $mediaId,
            'name' => $name,
            'sku' => $sku,
            'description' => is_string($description) ? app(ProductDescription::class)->sanitize($description) ?: null : null,
            'cost_price' => $costPrice,
            'target_margin' => $targetMargin,
            'price' => $price,
            'promotional_price' => $promotionalPrice,
            'promotion_enabled' => $promotionEnabled,
            'promotion_starts_at' => $promotionStartsAt,
            'promotion_ends_at' => $promotionEndsAt,
            'is_active' => $this->asBoolean($attributes['is_active'] ?? $product?->is_active ?? false),
            'is_featured' => $this->asBoolean($attributes['is_featured'] ?? $product?->is_featured ?? false),
        ];
        if (array_key_exists('sort_order', $attributes)) {
            $payload['sort_order'] = (int) $attributes['sort_order'];
        }

        return $payload;
    }

    /** @param array<string, mixed> $attributes */
    private function syncGallery(Product $product, array $attributes, ?int $mainMediaId): void
    {
        if (! array_key_exists('gallery_media_ids', $attributes)) {
            $ids = $product->images()->pluck('media.id')->map(fn ($id) => (int) $id)->all();
            if ($ids === [] && $mainMediaId !== null) {
                $ids = [$mainMediaId];
            }
        } else {
            $rawIds = $attributes['gallery_media_ids'];
            if (! is_array($rawIds) || count($rawIds) > 6) {
                throw new InvalidArgumentException('A galeria aceita no máximo seis imagens.');
            }
            $ids = array_map('intval', $rawIds);
            if (count(array_unique($ids)) !== count($ids)) {
                throw new InvalidArgumentException('A mesma imagem não pode ser adicionada mais de uma vez.');
            }
        }

        if ($mainMediaId !== null && ! in_array($mainMediaId, $ids, true)) {
            array_unshift($ids, $mainMediaId);
        }
        if (count($ids) > 6) {
            throw new InvalidArgumentException('A galeria aceita no máximo seis imagens.');
        }

        $validIds = Media::query()->whereIn('id', $ids)->where('disk', MediaService::DISK)
            ->whereIn('mime_type', $this->mediaService->supportedMimeTypes())
            ->get()->filter(fn (Media $media) => Storage::disk($media->disk)->exists($media->path))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($validIds) !== count($ids)) {
            throw new InvalidArgumentException('Uma ou mais imagens selecionadas não estão disponíveis na biblioteca.');
        }

        $ids = array_values($ids);
        $sync = [];
        foreach ($ids as $order => $id) {
            $sync[$id] = ['sort_order' => $order + 1];
        }
        // Move current positions out of the target range first so the unique
        // (product_id, sort_order) key also permits arbitrary reordering.
        foreach ($product->images()->pluck('media.id')->all() as $position => $currentId) {
            $product->images()->updateExistingPivot($currentId, ['sort_order' => 100 + $position]);
        }
        $product->images()->sync($sync);
    }

    /** @param array<string, mixed> $attributes */
    private function syncShippingProfile(Product $product, array $attributes): void
    {
        if (! array_key_exists('shipping_profile', $attributes)) {
            return;
        }

        $data = $attributes['shipping_profile'];
        if (! is_array($data)) {
            throw new InvalidArgumentException('Os dados para envio do produto são inválidos.');
        }

        $product->loadMissing('shippingProfile');
        $existing = $product->shippingProfile;
        $measurementFields = [
            'weight_kg', 'height_cm', 'width_cm', 'length_cm',
            'package_weight_kg', 'package_height_cm', 'package_width_cm', 'package_length_cm',
        ];
        $hasMeasurements = false;
        foreach ($measurementFields as $field) {
            $hasMeasurements = $hasMeasurements || filled($data[$field] ?? null);
        }

        $requiresShipping = $data['requires_shipping'] ?? null;
        if ($existing === null && $requiresShipping === null && ! $hasMeasurements) {
            // Produto antigo ou novo sem escolha explícita continua sem perfil:
            // ausência de dados não é convertida em produto sem envio.
            return;
        }
        if ($existing === null && $requiresShipping === null) {
            throw new InvalidArgumentException('Defina se o produto exige envio físico antes de informar medidas.');
        }

        $payload = [
            'product_id' => $product->id,
            'requires_shipping' => $requiresShipping === null
                ? $existing->requires_shipping
                : $this->asBoolean($requiresShipping),
        ];

        foreach ($measurementFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $this->shippingMeasurement($field, $data[$field]);
            } else {
                $payload[$field] = $existing?->getAttribute($field);
            }
        }

        $profile = $existing ?? new ProductShippingProfile;
        $profile->fill($payload)->save();
    }

    private function shippingMeasurement(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Informe medidas decimais válidas para envio.');
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (str_contains($field, 'weight')) {
            ShippingMeasurementConverter::kilogramsToGrams($normalized);
        } else {
            ShippingMeasurementConverter::centimetersToMillimeters($normalized);
        }

        return $normalized;
    }

    private function resolveSlug(mixed $slug, string $name, ?int $ignoreId = null): string
    {
        $explicit = is_string($slug) && trim($slug) !== '';
        $candidate = $explicit ? trim($slug) : Str::slug($name);
        if ($candidate === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $candidate) !== 1) {
            throw new InvalidArgumentException('O slug do produto é inválido.');
        }
        if (strlen($candidate) > 255) {
            throw new InvalidArgumentException('O slug do produto excede o limite ou já está em uso.');
        }
        if (! $explicit) {
            $base = $candidate;
            $suffix = 2;
            while (! $this->slugIsAvailable($candidate, $ignoreId)) {
                $candidate = $base.'-'.$suffix++;
            }
        } elseif (! $this->slugIsAvailable($candidate, $ignoreId)) {
            throw new InvalidArgumentException('O slug do produto excede o limite ou já está em uso.');
        }

        return $candidate;
    }

    private function money(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Informe um preço válido.');
        }
        $value = (string) $value;
        if (preg_match(self::MONEY_FORMAT, $value) !== 1) {
            throw new InvalidArgumentException('Informe um preço positivo com até duas casas decimais.');
        }
        [$whole, $fraction] = array_pad(preg_split('/[,.]/', $value, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return ltrim($whole, '0') === '' ? '0.'.$fraction : ltrim($whole, '0').'.'.$fraction;
    }

    private function percentage(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Informe um percentual válido.');
        }
        $normalized = str_replace(',', '.', (string) $value);
        if (preg_match('/^(\\d{1,2})(?:\\.(\\d{1,2}))?$/', $normalized, $parts) !== 1) {
            throw new InvalidArgumentException('Informe um percentual entre 0 e 99,99.');
        }

        return $parts[1].'.'.str_pad($parts[2] ?? '', 2, '0');
    }

    private function dateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return Carbon::createFromFormat('!Y-m-d\\TH:i', (string) $value, config('app.timezone'))
            ?->format('Y-m-d H:i:s')
            ?? throw new InvalidArgumentException('Informe uma data e hora válidas para a promoção.');
    }

    private function generatedSku(int $id): string
    {
        $base = 'PRD-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $candidate = $base;
        $suffix = 2;
        while (! $this->skuIsAvailable($candidate)) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function nextAvailableGeneratedSku(int $id, string $current): string
    {
        $base = 'PRD-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $suffix = str_starts_with($current, $base.'-') ? ((int) substr($current, strlen($base) + 1)) + 1 : 2;
        $candidate = $base.'-'.$suffix;
        while (! $this->skuIsAvailable($candidate)) {
            $candidate = $base.'-'.(++$suffix);
        }

        return $candidate;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }

    private static function decimalCents(string $value): int
    {
        [$whole, $fraction] = array_pad(preg_split('/[,.]/', $value, 2), 2, '');
        if (preg_match('/^\\d{1,10}$/', $whole) !== 1 || preg_match('/^\\d{0,2}$/', $fraction) !== 1) {
            throw new InvalidArgumentException('Informe um valor monetário válido.');
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function percentageBasisPoints(string $value): int
    {
        if (preg_match('/^\\d{1,2}(?:[,.]\\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Informe um percentual válido.');
        }
        [$whole, $fraction] = array_pad(preg_split('/[,.]/', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function divideRoundHalfUp(int $numerator, int $denominator): int
    {
        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        return $whole + (($remainder * 2 >= $denominator) ? 1 : 0);
    }

    private static function formatCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function formatPercent(int $basisPoints): string
    {
        $sign = $basisPoints < 0 ? '-' : '';
        $basisPoints = abs($basisPoints);

        return $sign.intdiv($basisPoints, 100).'.'.str_pad((string) ($basisPoints % 100), 2, '0', STR_PAD_LEFT);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = explode('.', $amount);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function nextOrder(): int
    {
        return ((int) Product::query()->max('sort_order')) + 1;
    }

    private function asBoolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on'], true);
    }
}
