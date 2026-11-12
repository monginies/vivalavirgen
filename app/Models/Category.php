<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'code', 'description', 'cover_path', 'sort'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /** Categorías visibles en la web: las que tienen algún artículo activo. */
    public function scopeForPortal(Builder $query): Builder
    {
        return $query->whereHas('articles', fn ($q) => $q->where('active', true))->orderBy('sort')->orderBy('name');
    }

    public function slug(): string
    {
        return Str::slug($this->name) ?: Str::lower($this->code);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::forPortal()->get()->first(fn (self $c) => $c->slug() === $slug || Str::lower($c->code) === $slug);
    }

    /** Portada de la colección: la foto propia o, si no tiene, la principal de su primer artículo con foto. */
    public function coverUrl(): ?string
    {
        if ($this->cover_path) {
            return asset('storage/'.$this->cover_path);
        }
        $photo = ArticlePhoto::query()
            ->whereHas('article', fn ($q) => $q->where('category_id', $this->id)->where('active', true))
            ->orderBy('position')->orderBy('id')->first();

        return $photo?->url();
    }
}
