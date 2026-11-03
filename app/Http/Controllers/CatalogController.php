<?php

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, ProductService $products, CategoryService $categoryService): View
    {
        $filters = $this->filters($request);
        $categorySlug = $filters['category'] ?? null;
        $categorySlug = is_string($categorySlug) && $categorySlug !== '' ? $categorySlug : null;
        $category = $categorySlug === null ? null : $categoryService->findPublicBySlug($categorySlug);
        abort_if($categorySlug !== null && $category === null, 404);

        return view('catalog.index', [
            'products' => $products->paginatePublic($categorySlug, 12, $category === null ? null : $categoryService->publicCategoryTreeIds($category), $filters),
            'categories' => $categoryService->publicCategories(),
            'selectedCategory' => $category,
            'filters' => $filters,
        ]);
    }

    public function category(Request $request, string $slug, ProductService $products, CategoryService $categoryService): View
    {
        $filters = $this->filters($request);
        $category = $categoryService->findPublicBySlug($slug);
        abort_if($category === null, 404);

        return view('catalog.index', [
            'products' => $products->paginatePublic($slug, 12, $categoryService->publicCategoryTreeIds($category), $filters),
            'categories' => $categoryService->publicCategories(),
            'selectedCategory' => $category,
            'filters' => $filters,
        ]);
    }

    public function show(string $slug, ProductService $products): View
    {
        $product = $products->findPublicBySlug($slug);
        abort_if($product === null, 404);

        return view('catalog.show', compact('product'));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $maximumPriceRules = ['nullable', 'numeric', 'min:0', 'max:9999999999.99'];
        if ($request->filled('price_min')) {
            $maximumPriceRules[] = 'gte:price_min';
        }

        $validated = $request->validate([
            'categoria' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', 'in:new,used'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_max' => $maximumPriceRules,
            'availability' => ['nullable', 'in:available,sold_out,unreported'],
            'sort' => ['nullable', 'in:relevance,price_asc,price_desc,newest'],
        ]);
        if (filled($validated['categoria'] ?? null)) {
            $validated['category'] = $validated['categoria'];
            unset($validated['categoria']);
        }

        return $validated;
    }
}
