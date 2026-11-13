<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una ampliación de la pre-reserva de un pedido (quién, cuándo y cuántos días). */
class OrderExtension extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'days', 'previous_expires_at', 'new_expires_at', 'user_id'];

    protected function casts(): array
    {
        return ['previous_expires_at' => 'datetime', 'new_expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
