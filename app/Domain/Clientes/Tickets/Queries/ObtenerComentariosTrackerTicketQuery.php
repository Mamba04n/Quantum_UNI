<?php

namespace App\Domain\Clientes\Tickets\Queries;

use App\Models\OrderTracker;
use App\Models\Ticket;
use Illuminate\Support\Collection;

class ObtenerComentariosTrackerTicketQuery
{
    public function ejecutar(?Ticket $ticket): Collection
    {
        if (! $ticket?->quotation_request_id) {
            return collect();
        }

        $tracker = OrderTracker::with('comments.user')
            ->whereHas('references', fn ($query) => $query
                ->where('source_module', 'quotation_requests')
                ->where('source_id', $ticket->quotation_request_id))
            ->first();

        return $tracker?->comments ?? collect();
    }
}
