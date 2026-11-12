<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un evento de la web de clientes (visita a una página, búsqueda, artículo añadido…). */
class WebEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Meses que se guardan los datos antes de borrarse solos. */
    public const RETENTION_MONTHS = 25;

    protected $fillable = ['type', 'visitor', 'client_id', 'article_id', 'category_id', 'path', 'term', 'value', 'source', 'device'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    public function prunable()
    {
        return static::where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }
}
