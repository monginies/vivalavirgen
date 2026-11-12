<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('categories.index', [
            'categories' => Category::withCount('articles')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('categories.form', ['category' => new Category]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        return redirect()->route('categories.index')->with('status', 'Categoría creada.');
    }

    public function edit(Category $category)
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('categories.index')->with('status', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->withTrashed()->exists()) {
            return back()->with('error', 'No se puede borrar una categoría que tiene artículos.');
        }
        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Categoría eliminada.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category)],
            'code' => ['required', 'alpha_num:ascii', 'max:10', Rule::unique('categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
