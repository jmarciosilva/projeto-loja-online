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
        $categorySlug = $request->query('categoria');
        $categorySlug = is_string($categorySlug) && $categorySlug !== '' ? $categorySlug : null;
        $category = $categorySlug === null ? null : $categoryService->findPublicBySlug($categorySlug);
        abort_if($categorySlug !== null && $category === null, 404);

        return view('catalog.index', [
            'products' => $products->paginatePublic($categorySlug),
            'categories' => $categoryService->publicCategories(),
            'selectedCategory' => $category,
        ]);
    }

    public function category(string $slug, ProductService $products, CategoryService $categoryService): View
    {
        $category = $categoryService->findPublicBySlug($slug);
        abort_if($category === null, 404);

        return view('catalog.index', [
            'products' => $products->paginatePublic($slug),
            'categories' => $categoryService->publicCategories(),
            'selectedCategory' => $category,
        ]);
    }

    public function show(string $slug, ProductService $products): View
    {
        $product = $products->findPublicBySlug($slug);
        abort_if($product === null, 404);

        return view('catalog.show', compact('product'));
    }
}
