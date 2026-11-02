<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(ProductService $products): View
    {
        $this->authorize('viewAny', Product::class);

        return view('admin.products.index', ['products' => $products->paginateAdmin()]);
    }

    public function create(CategoryService $categories, ProductService $products): View
    {
        $this->authorize('create', Product::class);
        $categoryOptions = $categories->adminOptions();
        $media = $products->adminMediaOptions();

        return view('admin.products.create', ['categories' => $categoryOptions, 'media' => $media]);
    }

    public function store(StoreProductRequest $request, ProductService $products): RedirectResponse
    {
        $products->create($request->validated());

        return redirect()->route('admin.products.index')->with('status', 'Produto criado com sucesso.');
    }

    public function edit(Product $product, CategoryService $categories, ProductService $products): View
    {
        $this->authorize('update', $product);
        $categoryOptions = $categories->adminOptions();
        $media = $products->adminMediaOptions();

        $product->load(['mainMedia', 'images', 'shippingProfile']);

        return view('admin.products.edit', ['product' => $product, 'categories' => $categoryOptions, 'media' => $media]);
    }

    public function update(UpdateProductRequest $request, Product $product, ProductService $products): RedirectResponse
    {
        $products->update($product, $request->validated());

        return redirect()->route('admin.products.index')->with('status', 'Produto atualizado com sucesso.');
    }

    public function toggle(Product $product, ProductService $products): RedirectResponse
    {
        $this->authorize('update', $product);
        $products->update($product, ['is_active' => ! $product->is_active]);

        return redirect()->route('admin.products.index')->with('status', 'Status do produto atualizado.');
    }

    public function destroy(Product $product, ProductService $products): RedirectResponse
    {
        $this->authorize('delete', $product);
        $products->delete($product);

        return redirect()->route('admin.products.index')->with('status', 'Produto excluído com sucesso.');
    }
}
