<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\ImageResizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $category = Category::create($this->validated($request));
        $this->saveCover($request, $category);

        return redirect()->route('categories.index')->with('status', 'Categoría creada.');
    }

    public function edit(Category $category)
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));
        $this->saveCover($request, $category);

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

    /** Foto de portada de la colección en la web. */
    private function saveCover(Request $request, Category $category): void
    {
        if (! $request->hasFile('cover') && ! $request->boolean('remove_cover')) {
            return;
        }
        if ($category->cover_path) {
            Storage::disk('public')->delete($category->cover_path);
        }
        $category->update(['cover_path' => $request->hasFile('cover')
            ? ImageResizer::store($request->file('cover'), 'categories', 1800)['path'] : null]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category)],
            'code' => ['required', 'alpha_num:ascii', 'max:10', Rule::unique('categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'cover' => ['nullable', 'image', 'max:20480'],
        ]);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        unset($data['cover']);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
