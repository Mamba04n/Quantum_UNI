<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Entrada',
            self::Out => 'Salida',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
