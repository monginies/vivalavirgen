<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::query()
            ->with(['category', 'mainPhoto'])
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

        $bundleIds = $articles->getCollection()->where('is_bundle', true)->pluck('id')->all();

        return view('articles.index', [
            'articles' => $articles,
            'bundleCapacity' => $bundleIds ? Availability::forArticles($bundleIds, today(), today()) : collect(),
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
            'article' => new Article(['category_id' => $request->category, 'active' => true, 'is_bundle' => $request->boolean('bundle')]),
            'categories' => $categories,
            'componentOptions' => $this->componentOptions(),
            'clientOptions' => $this->clientOptions(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $components, $clients] = $this->validated($request);
        $data['reference'] = Article::nextReference(Category::findOrFail($data['category_id']));
        $article = DB::transaction(function () use ($data, $components, $clients) {
            $article = Article::create($data);
            $article->components()->sync($components);
            $article->clients()->sync($clients);

            return $article;
        });
        if ($request->hasFile('photos')) {
            try {
                ArticlePhotoController::attach($article, $request->file('photos'));
            } catch (\RuntimeException $e) {
                session()->flash('error', $e->getMessage());
            }
        }
        if (! $article->is_bundle && $units = (int) $request->input('initial_units')) {
            $article->addUnits($units);
        }

        return redirect()->route('articles.show', $article)->with('status', 'Artículo creado.');
    }

    public function show(Article $article)
    {
        $article->load('category', 'units', 'photos', 'components.category', 'bundles', 'clients');

        $partIds = $article->components->pluck('id')->all();

        return view('articles.show', [
            'article' => $article,
            'today' => Availability::forArticles(array_merge([$article->id], $partIds), today(), today()),
            'statusCounts' => $article->units->countBy(fn ($u) => $u->status->value),
        ]);
    }

    public function edit(Article $article)
    {
        return view('articles.form', [
            'article' => $article->load('components', 'clients'),
            'categories' => Category::orderBy('name')->get(),
            'componentOptions' => $this->componentOptions($article),
            'clientOptions' => $this->clientOptions(),
        ]);
    }

    public function update(Request $request, Article $article)
    {
        [$data, $components, $clients] = $this->validated($request, $article);

        DB::transaction(function () use ($article, $data, $components, $clients) {
            $article->update($data);
            $article->components()->sync($components);
            $article->clients()->sync($clients);
        });

        return redirect()->route('articles.show', $article)->with('status', 'Artículo actualizado.');
    }

    public function destroy(Article $article)
    {
        if ($names = $article->bundles()->pluck('name')->join(', ')) {
            return back()->with('error', "No se puede eliminar: forma parte de estos combinados: {$names}. Quítalo antes de ellos.");
        }
        $article->units()->delete();
        $article->photos()->get()->each->delete();
        $article->delete();

        return redirect()->route('articles.index')->with('status', 'Artículo eliminado.');
    }

    public function addUnits(Request $request, Article $article)
    {
        abort_if($article->is_bundle, 422, 'Un producto combinado no tiene unidades propias: se añaden a sus piezas.');
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
            'photos' => $article ? ['prohibited'] : ['nullable', 'array', 'max:20'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'initial_units' => $article ? ['prohibited'] : ['nullable', 'integer', 'min:0', 'max:500'],
            'components' => ['nullable', 'array', 'max:50'],
            'components.*.article_id' => ['nullable', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'components.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'clients' => ['nullable', 'array'],
            'clients.*' => ['integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
        ]);
        $data['active'] = $request->boolean('active');
        $data['is_bundle'] = $request->boolean('is_bundle');
        $data['exclusive'] = $request->boolean('exclusive');
        $clients = $data['exclusive'] ? array_values(array_unique(array_map('intval', $data['clients'] ?? []))) : [];
        unset($data['photos'], $data['initial_units'], $data['components'], $data['clients']);

        $components = [];
        if ($data['is_bundle']) {
            if ($article && $article->units()->exists()) {
                throw ValidationException::withMessages(['is_bundle' => 'Este artículo tiene unidades propias: no puede ser un combinado. Crea un artículo nuevo para el combinado.']);
            }
            if ($article && $article->bundles()->exists()) {
                throw ValidationException::withMessages(['is_bundle' => 'Este artículo forma parte de otro combinado: un combinado no puede contener otro.']);
            }
            foreach ($request->input('components', []) as $row) {
                if (! empty($row['article_id']) && ! empty($row['quantity'])) {
                    $id = (int) $row['article_id'];
                    $components[$id] = ['quantity' => ($components[$id]['quantity'] ?? 0) + (int) $row['quantity']];
                }
            }
            if (! $components) {
                throw ValidationException::withMessages(['components' => 'Añade al menos una pieza al combinado.']);
            }
            if ($article && isset($components[$article->id])) {
                throw ValidationException::withMessages(['components' => 'Un combinado no puede contenerse a sí mismo.']);
            }
            if (Article::whereIn('id', array_keys($components))->where('is_bundle', true)->exists()) {
                throw ValidationException::withMessages(['components' => 'Las piezas tienen que ser artículos sueltos, no otros combinados.']);
            }
        }

        return [$data, $components, $clients];
    }

    /** Clientes que se pueden autorizar para un artículo exclusivo. */
    private function clientOptions()
    {
        return Client::orderBy('name')->get(['id', 'name', 'trade_name']);
    }

    /** Artículos que pueden ser pieza de un combinado (sueltos, sin contar el propio). */
    private function componentOptions(?Article $article = null)
    {
        return Article::query()
            ->where('is_bundle', false)
            ->when($article, fn ($q) => $q->whereKeyNot($article->id))
            ->with('category')
            ->orderBy('reference')
            ->get(['id', 'reference', 'name', 'category_id', 'active']);
    }
}
