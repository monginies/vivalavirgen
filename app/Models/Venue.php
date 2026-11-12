<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Fincas y lugares de celebración habituales, para elegirlos en los pedidos. */
class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'address', 'postal_code', 'city', 'distance_km', 'contact', 'notes', 'active'];

    protected function casts(): array
    {
        return ['distance_km' => 'integer', 'active' => 'boolean'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function place(): string
    {
        return trim($this->postal_code.' '.$this->city);
    }
}
