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
    public function index(Request $request)
    {
        $categories = Category::whereHas('articles', fn ($q) => $q->where('active', true))->orderBy('name')->get();
        $category = $categories->firstWhere('code', $request->categoria);

        $articles = Article::query()
            ->where('active', true)
            ->with('mainPhoto', 'category')
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('portal.catalog', [
            'articles' => $articles,
            'categories' => $categories,
            'category' => $category,
            'availability' => $this->availability($articles->pluck('id')->all()),
        ]);
    }

    public function show(string $reference)
    {
        $article = Article::where('active', true)->where('reference', $reference)
            ->with('photos', 'category')->firstOrFail();

        return view('portal.article', [
            'article' => $article,
            'inCart' => Cart::get()['items'][$article->id] ?? 0,
            'availability' => $this->availability([$article->id])[$article->id] ?? null,
        ]);
    }

    /** Si el carrito ya tiene fecha, se muestran las unidades libres para ese fin de semana. */
    private function availability(array $ids): array
    {
        $period = Cart::period();
        if (! $period || ! $ids) {
            return [];
        }

        return Availability::forArticles($ids, ...$period)->map(fn ($a) => max(0, $a['available']))->all();
    }
}
