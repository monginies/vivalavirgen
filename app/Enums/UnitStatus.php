<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Rented = 'rented';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Rented => 'Alquilada',
            self::Maintenance => 'En reparación',
            self::Retired => 'De baja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Rented => 'blue',
            self::Maintenance => 'amber',
            self::Retired => 'grey',
        };
    }
}
