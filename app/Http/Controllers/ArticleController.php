<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::query()
            ->with('category')
            ->withCount([
                'units',
                'units as available_count' => fn ($q) => $q->where('status', UnitStatus::Available),
            ])
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")))
            ->when($request->category, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('reference')
            ->paginate(25)
            ->withQueryString();

        return view('articles.index', [
            'articles' => $articles,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        if ($categories->isEmpty()) {
            return redirect()->route('categories.create')->with('error', 'Crea primero una categoría.');
        }

        return view('articles.form', [
            'article' => new Article(['category_id' => $request->category, 'active' => true]),
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['reference'] = Article::nextReference(Category::findOrFail($data['category_id']));
        $data['photo_path'] = $this->storePhoto($request);

        $article = Article::create($data);
        if ($units = (int) $request->input('initial_units')) {
            $article->addUnits($units);
        }

        return redirect()->route('articles.show', $article)->with('status', 'Artículo creado.');
    }

    public function show(Article $article)
    {
        $article->load('category', 'units');

        return view('articles.show', [
            'article' => $article,
            'statusCounts' => $article->units->countBy(fn ($u) => $u->status->value),
        ]);
    }

    public function edit(Article $article)
    {
        return view('articles.form', [
            'article' => $article,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $data = $this->validated($request, $article);

        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($article->photo_path) {
                Storage::disk('public')->delete($article->photo_path);
            }
            $data['photo_path'] = $this->storePhoto($request);
        }

        $article->update($data);

        return redirect()->route('articles.show', $article)->with('status', 'Artículo actualizado.');
    }

    public function destroy(Article $article)
    {
        $article->units()->delete();
        $article->delete();

        return redirect()->route('articles.index')->with('status', 'Artículo eliminado.');
    }

    public function addUnits(Request $request, Article $article)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'acquired_at' => ['nullable', 'date'],
        ]);
        $article->addUnits($data['count'], $data['acquired_at'] ?? null);

        return back()->with('status', "Se han añadido {$data['count']} unidades.");
    }

    public function labels(Request $request, Article $article)
    {
        $units = $article->units()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->get();

        return view('articles.labels', compact('article', 'units'));
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'rental_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'replacement_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'initial_units' => $article ? ['prohibited'] : ['nullable', 'integer', 'min:0', 'max:500'],
        ]);
        $data['active'] = $request->boolean('active');
        unset($data['photo'], $data['initial_units']);

        return $data;
    }

    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('articles', 'public')
            : null;
    }
}
