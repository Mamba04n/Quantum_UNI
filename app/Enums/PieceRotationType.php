<?php

namespace App\Enums;

enum PieceRotationType: string
{
    case High = 'alta_rotacion';
    case Low = 'baja_rotacion';
    case RoutineMaintenance = 'mantenimiento_rutinario';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Alta rotacion',
            self::Low => 'Baja rotacion',
            self::RoutineMaintenance => 'Mantenimiento rutinario',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
