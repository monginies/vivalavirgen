<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Códigos postales de España con coordenadas (GeoNames, CC BY 4.0). Se cargan con `php artisan postal-codes:import`. */
class PostalCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'place', 'municipality', 'province', 'lat', 'lon'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lon' => 'float'];
    }

    /** @return Collection<int, PostalCode> */
    public static function lookup(?string $code): Collection
    {
        $code = preg_replace('~\D~', '', (string) $code);

        return strlen($code) === 5 ? static::where('code', $code)->orderBy('id')->get() : collect();
    }

    /**
     * El punto que mejor representa ese CP: la localidad que coincide con la población escrita,
     * si no el núcleo principal del municipio, si no la primera entrada.
     */
    public static function bestMatch(Collection $rows, ?string $city): ?self
    {
        if ($rows->isEmpty()) {
            return null;
        }
        $norm = fn ($v) => Str::lower(Str::ascii(trim((string) $v)));
        $city = $norm($city);

        return ($city !== '' ? $rows->first(fn ($r) => $norm($r->place) === $city) : null)
            ?? ($city !== '' ? $rows->first(fn ($r) => $norm($r->municipality) === $city && $norm($r->place) === $city) : null)
            ?? $rows->first(fn ($r) => $norm($r->place) === $norm($r->municipality))
            ?? $rows->first();
    }

    public function label(): string
    {
        $same = fn ($a, $b) => Str::lower(Str::ascii((string) $a)) === Str::lower(Str::ascii((string) $b));
        $parts = $this->municipality && $same($this->place, $this->municipality) ? [$this->municipality] : [$this->place];
        if ($this->municipality && ! $same($this->municipality, $this->place)) {
            $parts[] = $this->municipality;
        }
        if ($this->province && Str::lower($this->province) !== Str::lower($this->municipality ?? '')) {
            $parts[] = $this->province;
        }

        return implode(', ', $parts);
    }
}
