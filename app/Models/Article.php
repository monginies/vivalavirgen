<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'reference', 'name', 'description', 'dimensions',
        'rental_price', 'replacement_price', 'photo_path', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'rental_price' => 'decimal:2',
            'replacement_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }
}
