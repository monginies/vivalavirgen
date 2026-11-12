<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'number', 'client_id', 'status', 'source', 'event_date', 'venue_id', 'start_date', 'end_date',
        'destination_address', 'destination_city', 'destination_postal_code', 'distance_km', 'trucks',
        'transport_manual', 'transport_amount', 'discount_percent', 'deposit_amount',
        'vat_rate', 'expires_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'event_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'expires_at' => 'datetime',
            'distance_km' => 'integer',
            'transport_manual' => 'boolean',
            'transport_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Pedidos que retienen material ahora mismo (las pre-reservas vencidas no cuentan aunque aún no se hayan marcado). */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query
            ->whereIn('status', OrderStatus::blocking())
            ->where(fn ($q) => $q
                ->whereNotIn('status', OrderStatus::expirable())
                ->orWhereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    public function isOverdue(): bool
    {
        return $this->status->isExpirable() && $this->expires_at && $this->expires_at->isPast();
    }

    /** Marca como caducadas las pre-reservas vencidas. Devuelve cuántas. */
    public static function expireOverdue(): int
    {
        return static::query()
            ->whereIn('status', OrderStatus::expirable())
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => OrderStatus::Expired, 'updated_at' => now()]);
    }

    public static function nextNumber(): string
    {
        $prefix = 'PED-'.now()->year.'-';
        $last = static::where('number', 'like', $prefix.'%')->max('number');
        $n = $last ? (int) substr($last, strlen($prefix)) : 0;

        return $prefix.str_pad($n + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Si el evento cae en fin de semana (viernes, sábado o domingo), el material
     * queda reservado como mínimo de viernes a domingo. Las fechas que ya cubran
     * más días se respetan.
     *
     * @return array{0: string, 1: string} [entrega, recogida] en Y-m-d
     */
    public static function weekendBlock(?string $event, ?string $start, ?string $end): array
    {
        if ($event) {
            $e = CarbonImmutable::parse($event);
            if (in_array($e->dayOfWeek, [CarbonImmutable::FRIDAY, CarbonImmutable::SATURDAY, CarbonImmutable::SUNDAY], true)) {
                $friday = $e->dayOfWeek === CarbonImmutable::FRIDAY ? $e : $e->previous(CarbonImmutable::FRIDAY);
                $sunday = $friday->addDays(2);
                $start = $start ? min(CarbonImmutable::parse($start), $friday) : $friday;
                $end = $end ? max(CarbonImmutable::parse($end), $sunday) : $sunday;
            }
            $start ??= $e;
            $end ??= $e;
        }

        return [
            $start ? CarbonImmutable::parse($start)->toDateString() : null,
            $end ? CarbonImmutable::parse($end)->toDateString() : null,
        ];
    }

    /** Transporte: 2 trayectos × km × €/km × camiones, con mínimo. Sin km, 0. */
    public static function calculateTransport(?float $km, int $trucks): float
    {
        if (! $km) {
            return 0;
        }
        $amount = 2 * $km * Setting::number('transport_price_per_km') * max(1, $trucks);

        return round(max($amount, Setting::number('transport_minimum')), 2);
    }

    // --- Importes (sin IVA salvo el total) ---

    public function subtotal(): float
    {
        return round($this->lines->sum(fn ($l) => $l->quantity * $l->unit_price), 2);
    }

    public function discountAmount(): float
    {
        return round($this->subtotal() * $this->discount_percent / 100, 2);
    }

    public function taxableBase(): float
    {
        return round($this->subtotal() - $this->discountAmount() + $this->transport_amount, 2);
    }

    public function vatAmount(): float
    {
        return round($this->taxableBase() * $this->vat_rate / 100, 2);
    }

    public function total(): float
    {
        return round($this->taxableBase() + $this->vatAmount(), 2);
    }

    public function destination(): string
    {
        return collect([$this->venue?->name, $this->destination_address, trim($this->destination_postal_code.' '.$this->destination_city)])
            ->filter()->unique()->join(', ');
    }
}
