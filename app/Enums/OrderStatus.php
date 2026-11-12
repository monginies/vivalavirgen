<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case ProformaSent = 'proforma_sent';
    case Paid = 'paid';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitado',
            self::Accepted => 'Aceptado',
            self::ProformaSent => 'Proforma enviada',
            self::Paid => 'Pagado',
            self::Delivered => 'Entregado',
            self::Returned => 'Devuelto',
            self::Closed => 'Cerrado',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Caducado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested, self::Accepted, self::ProformaSent => 'amber',
            self::Paid => 'green',
            self::Delivered, self::Returned => 'blue',
            self::Closed, self::Cancelled, self::Expired => 'grey',
        };
    }

    /** Estados que retienen material. */
    public static function blocking(): array
    {
        return [self::Requested, self::Accepted, self::ProformaSent, self::Paid, self::Delivered, self::Returned];
    }

    /** Estados de pre-reserva: caducan si no se pagan a tiempo. */
    public static function expirable(): array
    {
        return [self::Requested, self::Accepted, self::ProformaSent];
    }

    /** Material fuera del almacén: sigue bloqueado hasta que se cierra, aunque pase la fecha de recogida. */
    public static function outOfWarehouse(): array
    {
        return [self::Delivered, self::Returned];
    }

    public function isBlocking(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    public function isExpirable(): bool
    {
        return in_array($this, self::expirable(), true);
    }

    /** Se pueden cambiar líneas, fechas y destino. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Requested, self::Accepted, self::ProformaSent, self::Paid], true);
    }

    /** Siguiente paso normal del flujo, con el texto del botón. */
    public function next(): ?array
    {
        return match ($this) {
            self::Requested => [self::Accepted, 'Aceptar pedido'],
            self::Accepted => [self::ProformaSent, 'Marcar proforma enviada'],
            self::ProformaSent => [self::Paid, 'Marcar como pagado'],
            self::Paid => [self::Delivered, 'Marcar como entregado'],
            self::Delivered => [self::Returned, 'Marcar como devuelto'],
            self::Returned => [self::Closed, 'Dar entrada al material y cerrar'],
            default => null,
        };
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Requested, self::Accepted, self::ProformaSent, self::Paid], true);
    }
}
