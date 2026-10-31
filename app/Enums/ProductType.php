<?php

namespace App\Enums;

enum ProductType: string
{
    case Piece = 'pieza';
    case Service = 'servicio';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Pieza',
            self::Service => 'Servicio',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
