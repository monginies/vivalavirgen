<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una unidad física que ha salido con un pedido. */
class OrderUnit extends Model
{
    public const RETURN_LABELS = ['ok' => 'Devuelta', 'damaged' => 'Rota / para reparar', 'lost' => 'Perdida'];

    public $timestamps = false;

    protected $fillable = ['order_id', 'unit_id', 'out_at', 'out_by', 'in_at', 'in_by', 'return_status'];

    protected function casts(): array
    {
        return ['out_at' => 'datetime', 'in_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /** Sigue fuera: ni ha vuelto ni se ha dado por perdida. */
    public function isOut(): bool
    {
        return $this->return_status === null;
    }
}
