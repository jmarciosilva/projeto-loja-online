<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Centraliza alterações auditáveis do saldo físico. */
class StockService
{
    /** @return array{quantity_on_hand:int, quantity_reserved:int, available:int, minimum_quantity:int, status:string} */
    public function summary(Product $product): array
    {
        $stock = $product->stock;
        if ($stock === null || ! $stock->stock_control_enabled) {
            return ['quantity_on_hand' => 0, 'quantity_reserved' => 0, 'available' => 0, 'minimum_quantity' => 0, 'status' => 'unreported'];
        }

        return [
            'quantity_on_hand' => $stock->quantity_on_hand,
            'quantity_reserved' => $stock->quantity_reserved,
            'available' => $stock->availableQuantity(),
            'minimum_quantity' => $stock->minimum_quantity,
            'status' => $stock->publicAvailability(),
        ];
    }

    public function configureInitial(Product $product, bool $enabled, int $quantity, int $minimum, ?User $actor, string $reason = 'Definição do saldo inicial', ?string $referenceType = null, ?string $referenceId = null): ProductStock
    {
        if ($enabled && ! $actor?->canManageStock()) {
            throw new InvalidArgumentException('Você não tem permissão para configurar o saldo inicial.');
        }
        if ($quantity < 0 || $minimum < 0 || $quantity > 1000000 || $minimum > 1000000) {
            throw new InvalidArgumentException('As quantidades devem estar entre zero e 1.000.000.');
        }
        if (! $enabled && $quantity !== 0) {
            throw new InvalidArgumentException('Desative o controle de estoque ou informe saldo inicial zero.');
        }

        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Informe um motivo válido para o saldo inicial.');
        }

        return DB::transaction(function () use ($product, $enabled, $quantity, $minimum, $actor, $reason, $referenceType, $referenceId): ProductStock {
            $stock = ProductStock::query()->where('product_id', $product->id)->lockForUpdate()->first();
            if ($stock !== null && ($stock->stock_control_enabled || $stock->movements()->exists())) {
                throw new InvalidArgumentException('O estoque já foi configurado. Use uma movimentação para alterar o saldo.');
            }
            if ($stock === null) {
                $stock = new ProductStock;
            }
            $stock->forceFill([
                'product_id' => $product->id,
                'stock_control_enabled' => $enabled,
                'quantity_on_hand' => $enabled ? $quantity : 0,
                'quantity_reserved' => 0,
                'minimum_quantity' => $minimum,
            ])->save();
            if ($enabled) {
                $this->movement($product, $stock, 'initial_balance', $quantity, 0, $quantity, trim($reason), $actor, $referenceType, $referenceId);
            }

            return $stock;
        });
    }

    public function entry(Product $product, int $quantity, string $reason, User $actor): StockMovement
    {
        return $this->move($product, 'entry', $quantity, $reason, $actor);
    }

    public function manualExit(Product $product, int $quantity, string $reason, User $actor): StockMovement
    {
        return $this->move($product, 'manual_exit', -$quantity, $reason, $actor);
    }

    public function positiveAdjustment(Product $product, int $quantity, string $reason, User $actor): StockMovement
    {
        return $this->move($product, 'positive_adjustment', $quantity, $reason, $actor);
    }

    public function negativeAdjustment(Product $product, int $quantity, string $reason, User $actor): StockMovement
    {
        return $this->move($product, 'negative_adjustment', -$quantity, $reason, $actor);
    }

    /** @return Collection<int, StockMovement> */
    public function history(Product $product, int $perPage = 50)
    {
        return $product->stockMovements()->with('user:id,name')->limit($perPage)->get();
    }

    private function move(Product $product, string $type, int $delta, string $reason, User $actor): StockMovement
    {
        if (! $actor->canManageStock()) {
            throw new InvalidArgumentException('Você não tem permissão para movimentar estoque.');
        }
        $positiveType = in_array($type, ['entry', 'positive_adjustment'], true);
        $negativeType = in_array($type, ['manual_exit', 'negative_adjustment'], true);
        if ((! $positiveType && ! $negativeType)
            || ($positiveType && $delta <= 0) || ($negativeType && $delta >= 0)
            || abs($delta) > 1000000 || trim($reason) === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Informe uma quantidade válida e um motivo para a movimentação.');
        }

        return DB::transaction(function () use ($product, $type, $delta, $reason, $actor): StockMovement {
            $stock = ProductStock::query()->where('product_id', $product->id)->lockForUpdate()->first();
            if ($stock === null || ! $stock->stock_control_enabled) {
                throw new InvalidArgumentException('Configure o controle de estoque antes de registrar movimentações.');
            }
            $before = $stock->quantity_on_hand;
            $after = $before + $delta;
            if ($after < $stock->quantity_reserved) {
                throw new InvalidArgumentException('A movimentação não pode reduzir o estoque abaixo das unidades reservadas.');
            }
            if ($type === 'manual_exit' && abs($delta) > $stock->availableQuantity()) {
                throw new InvalidArgumentException('A saída não pode exceder o estoque disponível.');
            }
            if ($after > 1000000) {
                throw new InvalidArgumentException('O saldo excede o limite permitido.');
            }

            $stock->quantity_on_hand = $after;
            $stock->save();

            return $this->movement($product, $stock, $type, $delta, $before, $after, trim($reason), $actor);
        });
    }

    private function movement(Product $product, ProductStock $stock, string $type, int $delta, int $before, int $after, string $reason, ?User $actor, ?string $referenceType = null, ?string $referenceId = null): StockMovement
    {
        return StockMovement::query()->forceCreate([
            'product_id' => $product->id,
            'product_stock_id' => $stock->id,
            'type' => $type,
            'quantity_delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason,
            'user_id' => $actor?->id,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
