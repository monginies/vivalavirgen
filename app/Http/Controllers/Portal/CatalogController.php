<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Services\Availability;
use App\Support\Cart;
use Illuminate\Http\Request;

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
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('dimensions', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('portal.collection', [
            'category' => $category,
            'title' => $title,
            'articles' => $articles,
            'availability' => $this->availability($articles->pluck('id')->all()),
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
