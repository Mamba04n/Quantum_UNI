<?php

namespace App\Enums;

enum TechnicalCategory: string
{
    case Electrical = 'electrico';
    case Mechanical = 'mecanico';
    case Hydraulic = 'hidraulico';
    case Pneumatic = 'neumatico';
    case Filtration = 'filtracion';
    case Lubrication = 'lubricacion';
    case Engine = 'motor';
    case Transmission = 'transmision';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Electrical => 'Eléctrico',
            self::Mechanical => 'Mecánico',
            self::Hydraulic => 'Hidráulico',
            self::Pneumatic => 'Neumático',
            self::Filtration => 'Filtración',
            self::Lubrication => 'Lubricación',
            self::Engine => 'Motor',
            self::Transmission => 'Transmisión',
            self::Other => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
