<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Services\Availability;
use App\Support\Cart;
use App\Support\Tracker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    /** Portada: imagen principal y colecciones. Con ?q= muestra resultados de búsqueda. */
    public function index(Request $request)
    {
        if ($request->filled('categoria') && $c = Category::findBySlug(strtolower($request->categoria))) {
            return redirect()->route('portal.collection', $c->slug());
        }
        if ($request->filled('q')) {
            return $this->listing($request, null, 'Búsqueda: '.$request->q);
        }

        return view('portal.catalog', [
            'categories' => Category::forPortal()->get(),
            'total' => Article::where('active', true)->count(),
        ]);
    }

    public function collection(Request $request, string $slug)
    {
        $category = Category::findBySlug($slug) ?? abort(404);

        return $this->listing($request, $category, $category->name);
    }

    public function show(string $reference)
    {
        $article = Article::where('active', true)->where('reference', $reference)
            ->with('photos', 'category')->firstOrFail();

        return view('portal.article', [
            'article' => $article,
            'inCart' => Cart::get()['items'][$article->id] ?? 0,
            'availability' => $this->availability([$article->id])[$article->id] ?? null,
            'related' => Article::where('active', true)->where('category_id', $article->category_id)->whereKeyNot($article->id)
                ->with('mainPhoto')->inRandomOrder()->take(4)->get(),
        ]);
    }

    private function listing(Request $request, ?Category $category, string $title)
    {
        $articles = Article::query()
            ->where('active', true)
            ->with('mainPhoto', 'category')
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($request->q, fn ($q, $term) => $q->search($term))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        if (filled($request->q) && $articles->onFirstPage()) {
            Tracker::record('search', [
                'term' => mb_substr(mb_strtolower(trim((string) $request->q)), 0, 100),
                'value' => $articles->total(),
                'category_id' => $category?->id,
            ]);
        }

        return view('portal.collection', [
            'category' => $category,
            'title' => $title,
            'articles' => $articles,
            'availability' => $this->availability($articles->pluck('id')->all()),
        ]);
    }

    /** Sugerencias mientras se escribe en el buscador. */
    public function suggest(Request $request)
    {
        $term = mb_substr(trim((string) $request->query('q')), 0, 80);
        if (mb_strlen($term) < 2) {
            return response()->json(['articles' => [], 'categories' => []]);
        }

        $first = addcslashes(strtok($term, ' '), '%_\\');
        $articles = Article::where('active', true)->search($term)
            ->with('mainPhoto', 'category')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$first.'%', '% '.$first.'%'])
            ->orderBy('name')->limit(6)->get();

        $categories = Category::forPortal()->get()
            ->filter(fn (Category $c) => str_contains(Str::lower(Str::ascii($c->name)), Str::lower(Str::ascii($term))))
            ->take(3);

        return response()->json([
            'articles' => $articles->map(fn (Article $a) => [
                'name' => $a->name,
                'meta' => collect([$a->category?->name, $a->dimensions])->filter()->join(' · '),
                'img' => $a->photoUrl(),
                'url' => route('portal.article', $a->reference),
            ])->values(),
            'categories' => $categories->map(fn (Category $c) => [
                'name' => $c->name,
                'url' => route('portal.collection', $c->slug()),
            ])->values(),
            'all' => route('portal.home', ['q' => $term]),
        ]);
    }

    /** Si el pedido ya tiene fecha, se muestran las unidades libres para ese fin de semana. */
    private function availability(array $ids): array
    {
        $period = Cart::period();
        if (! $period || ! $ids) {
            return [];
        }

        return Availability::forArticles($ids, ...$period)->map(fn ($a) => max(0, $a['available']))->all();
    }
}
