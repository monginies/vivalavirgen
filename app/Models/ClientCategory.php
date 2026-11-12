<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de cliente con su descuento sobre el precio base (particular, wedding planner…). */
class ClientCategory extends Model
{
    protected $fillable = ['name', 'discount_percent', 'sort'];

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2'];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
