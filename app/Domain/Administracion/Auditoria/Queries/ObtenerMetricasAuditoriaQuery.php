<?php

namespace App\Domain\Administracion\Auditoria\Queries;

use App\Models\StockMove;
use App\Models\Ticket;

class ObtenerMetricasAuditoriaQuery
{
    public function ejecutar(): array
    {
        return [
            'todayLogs' => app(UnifiedActivityQuery::class)->base()->whereDate('created_at', today())->count(),
            'activeToday' => app(UnifiedActivityQuery::class)->base()->whereDate('created_at', today())->distinct()->count('user_id'),
            'totalActions' => app(UnifiedActivityQuery::class)->base()->count(),
            'todayMoves' => StockMove::whereDate('movement_date', today())->count(),
            'todayTickets' => Ticket::whereDate('created_at', today())->count(),
        ];
    }
}
