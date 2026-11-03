<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(CategoryService $categories): View
    {
        $this->authorize('viewAny', Category::class);

        return view('admin.categories.index', ['categories' => $categories->paginate()]);
    }

    public function create(CategoryService $categories): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', ['category' => new Category, 'parentOptions' => $categories->parentOptions()]);
    }

    public function store(StoreCategoryRequest $request, CategoryService $categories): RedirectResponse
    {
        $categories->create($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Categoria criada com sucesso.');
    }

    public function edit(Category $category, CategoryService $categories): View
    {
        $this->authorize('update', $category);

        return view('admin.categories.edit', ['category' => $category, 'parentOptions' => $categories->parentOptions()->reject(fn (Category $option) => $option->is($category))->values()]);
    }

    public function update(UpdateCategoryRequest $request, Category $category, CategoryService $categories): RedirectResponse
    {
        $categories->update($category, $request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Categoria atualizada com sucesso.');
    }

    public function toggle(Category $category, CategoryService $categories): RedirectResponse
    {
        $this->authorize('update', $category);
        $categories->update($category, ['is_active' => ! $category->is_active]);

        return redirect()->route('admin.categories.index')->with('status', 'Status da categoria atualizado.');
    }

    public function destroy(Category $category, CategoryService $categories): RedirectResponse
    {
        $this->authorize('delete', $category);
        $categories->delete($category);

        return redirect()->route('admin.categories.index')->with('status', 'Categoria excluída com sucesso.');
    }
}
