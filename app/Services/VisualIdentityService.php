<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Collection;

/**
 * Camada autoritativa da identidade visual da loja — logo e favicon.
 *
 * A configuração guarda apenas a **identidade** da mídia (`Media.id`), nunca
 * caminho nem URL: um path persistido congelaria o armazenamento de um
 * ambiente, e trocar o disco ou o domínio quebraria todas as referências de
 * uma vez. A URL é sempre derivada da própria mídia, pelo contrato da F2.7.
 *
 * ```text
 * SiteSetting → Media.id → Media → Storage::disk($media->disk)->url($media->path)
 * ```
 *
 * A F2.7 permanece sem conhecer logo ou favicon: é esta subfase que registra
 * seus verificadores no `MediaUsageRegistry`, e não o contrário.
 */
class VisualIdentityService
{
    /**
     * Chave da configuração que guarda o `Media.id` da logo.
     */
    public const LOGO_KEY = 'site.logo_media_id';

    /**
     * Chave da configuração que guarda o `Media.id` do favicon.
     */
    public const FAVICON_KEY = 'site.favicon_media_id';

    public function __construct(
        private readonly SiteSettingService $siteSettings,
        private readonly MediaService $media,
    ) {}

    /**
     * `Media.id` configurado como logo, ou `null` quando não há logo.
     */
    public function logoMediaId(): ?int
    {
        return $this->mediaId(self::LOGO_KEY);
    }

    /**
     * `Media.id` configurado como favicon, ou `null` quando não há favicon.
     */
    public function faviconMediaId(): ?int
    {
        return $this->mediaId(self::FAVICON_KEY);
    }

    /**
     * Mídia configurada como logo, se ela ainda existir.
     */
    public function logo(): ?Media
    {
        return $this->resolve($this->logoMediaId());
    }

    /**
     * Mídia configurada como favicon, se ela ainda existir.
     */
    public function favicon(): ?Media
    {
        return $this->resolve($this->faviconMediaId());
    }

    /**
     * URL pública da mídia, derivada do disco em que ela foi gravada.
     */
    public function url(Media $media): string
    {
        return $this->media->url($media);
    }

    /**
     * Persiste logo e favicon em um único lote atômico.
     *
     * As duas chaves viajam juntas por `setMany()`: um submit da tela é uma
     * decisão só, e gravar uma e falhar na outra deixaria a identidade visual
     * pela metade. `null` é o contrato de ausência — a configuração passa a
     * existir com `type = null`, em vez de guardar `0` ou string vazia como
     * sentinela, que exigiriam de todo leitor saber qual valor "significa
     * nada".
     */
    public function save(?int $logoMediaId, ?int $faviconMediaId): void
    {
        $this->siteSettings->setMany([
            self::LOGO_KEY => $this->settingPayload($logoMediaId),
            self::FAVICON_KEY => $this->settingPayload($faviconMediaId),
        ]);
    }

    /**
     * A mídia está configurada como logo?
     */
    public function isLogo(Media $media): bool
    {
        return $media->id !== null && $media->id === $this->logoMediaId();
    }

    /**
     * A mídia está configurada como favicon?
     */
    public function isFavicon(Media $media): bool
    {
        return $media->id !== null && $media->id === $this->faviconMediaId();
    }

    /**
     * Mídias oferecidas ao administrador para escolher logo e favicon.
     *
     * A consulta vive aqui, e não na Blade: a tela apenas apresenta. A ordem é
     * `id DESC`, a mesma da biblioteca — a mídia recém-enviada aparece primeiro,
     * que é o caso de uso real de quem acabou de subir uma logo.
     *
     * @return Collection<int, Media>
     */
    public function availableMedia(): Collection
    {
        return Media::query()->orderByDesc('id')->get();
    }

    /**
     * Identidade visual pronta para o layout público.
     *
     * Devolve dados já resolvidos — URL derivada inclusive — para que a Blade
     * não precise consultar nada nem remontar caminho. Uma referência que
     * aponte para mídia inexistente vira `null`: o storefront degrada para o
     * fallback em vez de estourar 500.
     *
     * @return array{logo: null|array{url: string, alt: string, width: int, height: int}, favicon: null|array{url: string, mimeType: string}}
     */
    public function forPublicLayout(): array
    {
        $logo = $this->logo();
        $favicon = $this->favicon();

        return [
            'logo' => $logo === null ? null : [
                'url' => $this->url($logo),
                'alt' => (string) config('app.name'),
                'width' => $logo->width,
                'height' => $logo->height,
            ],
            'favicon' => $favicon === null ? null : [
                'url' => $this->url($favicon),
                'mimeType' => $favicon->mime_type,
            ],
        ];
    }

    /**
     * Lê a referência configurada, tolerando dados inconsistentes.
     *
     * O valor persistido pode ter sido manipulado fora da aplicação — direto no
     * banco, por exemplo. Só um inteiro positivo é aceito como referência; o
     * resto é tratado como ausência, e não como erro, porque quem lê isto é
     * também a vitrine pública.
     */
    private function mediaId(string $key): ?int
    {
        $value = $this->siteSettings->get($key);

        if (! is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }

    /**
     * Resolve a mídia pelo id, ou `null` se ela já não existir.
     */
    private function resolve(?int $mediaId): ?Media
    {
        if ($mediaId === null) {
            return null;
        }

        return Media::query()->find($mediaId);
    }

    /**
     * Monta o par tipo/valor esperado pelo `SiteSettingService`.
     *
     * @return array{type: string, value: ?int}
     */
    private function settingPayload(?int $mediaId): array
    {
        return $mediaId === null
            ? ['type' => 'null', 'value' => null]
            : ['type' => 'integer', 'value' => $mediaId];
    }
}
