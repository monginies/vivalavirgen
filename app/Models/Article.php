<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'is_bundle', 'exclusive', 'reference', 'name', 'description', 'dimensions',
        'rental_price', 'replacement_price', 'photo_path', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'rental_price' => 'decimal:2',
            'replacement_price' => 'decimal:2',
            'active' => 'boolean',
            'is_bundle' => 'boolean',
            'exclusive' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Piezas de un producto combinado (pivot `quantity` = piezas por combinado). */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_components', 'bundle_id', 'component_id')
            ->withPivot('quantity')->orderBy('reference');
    }

    /** «2 × Butaca esparto, 1 × Mesa Bea» para un combinado; null si no lo es o no tiene piezas. */
    public function componentsSummary(): ?string
    {
        if (! $this->is_bundle || $this->components->isEmpty()) {
            return null;
        }

        return $this->components->map(fn (Article $c) => "{$c->pivot->quantity} × {$c->name}")->join(', ');
    }

    /** Clientes autorizados a alquilar un artículo exclusivo. */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'article_client')->orderBy('name');
    }

    /** Combinados de los que forma parte este artículo. */
    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_components', 'component_id', 'bundle_id')
            ->withPivot('quantity')->orderBy('reference');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('code');
    }

    /** Siguiente referencia libre para una categoría: SIL-0001, SIL-0002… */
    public static function nextReference(Category $category): string
    {
        $prefix = strtoupper($category->code).'-';
        $last = static::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->pluck('reference')
            ->map(fn ($r) => (int) Str::after($r, $prefix))
            ->max() ?? 0;

        return $prefix.str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }

    /** Crea $count unidades nuevas con código correlativo y token QR. */
    public function addUnits(int $count, ?string $acquiredAt = null): void
    {
        DB::transaction(function () use ($count, $acquiredAt) {
            $prefix = $this->reference.'-';
            $last = Unit::withTrashed()
                ->where('article_id', $this->id)
                ->lockForUpdate()
                ->pluck('code')
                ->map(fn ($c) => (int) Str::after($c, $prefix))
                ->max() ?? 0;

            for ($i = 1; $i <= $count; $i++) {
                $this->units()->create([
                    'code' => $prefix.str_pad($last + $i, 3, '0', STR_PAD_LEFT),
                    'qr_token' => (string) Str::uuid(),
                    'status' => UnitStatus::Available,
                    'acquired_at' => $acquiredAt,
                ]);
            }
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ArticlePhoto::class)->orderBy('position')->orderBy('id');
    }

    public function mainPhoto(): HasOne
    {
        return $this->hasOne(ArticlePhoto::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    /** Búsqueda de la web: todas las palabras deben aparecer (en cualquier orden) en el nombre, la descripción, las medidas, la referencia o la categoría. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $words = array_slice(array_filter(preg_split('/\s+/u', trim((string) $term))), 0, 6);
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('dimensions', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)));
        }

        return $query;
    }

    /** Miniatura de la foto principal (listados). */
    public function photoUrl(): ?string
    {
        return $this->mainPhoto?->thumbUrl();
    }
}
