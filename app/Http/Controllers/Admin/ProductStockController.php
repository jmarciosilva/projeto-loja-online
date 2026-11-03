<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockRequest;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class ProductStockController extends Controller
{
    public function edit(Product $product, StockService $stocks): View
    {
        $this->authorize('viewStockMovements', $product);
        $product->load('stock');
        $movements = $stocks->history($product);

        return view('admin.products.stock', compact('product', 'movements'));
    }

    public function store(StoreStockRequest $request, Product $product, StockService $stocks): RedirectResponse
    {
        $data = $request->validated();

        try {
            if ($data['mode'] === 'initial') {
                $stocks->configureInitial(
                    $product,
                    (bool) $data['stock_control_enabled'],
                    (bool) $data['stock_control_enabled'] ? (int) $data['quantity'] : 0,
                    (int) ($data['minimum_quantity'] ?? 0),
                    $request->user(),
                );
            } else {
                DB::transaction(function () use ($stocks, $product, $data, $request): void {
                    $method = match ($data['type']) {
                        'entry' => 'entry',
                        'manual_exit' => 'manualExit',
                        'positive_adjustment' => 'positiveAdjustment',
                        'negative_adjustment' => 'negativeAdjustment',
                    };
                    $stocks->{$method}($product, (int) $data['quantity'], $data['reason'], $request->user());
                });
            }
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['stock' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.stock', $product)->with('status', 'Estoque atualizado e movimentação registrada.');
    }
}
