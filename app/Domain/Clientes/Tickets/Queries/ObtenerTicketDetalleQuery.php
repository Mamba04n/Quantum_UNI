<?php

namespace App\Domain\Clientes\Tickets\Queries;

use App\Models\Ticket;

class ObtenerTicketDetalleQuery
{
    public function ejecutar(?int $ticketId): ?Ticket
    {
        if (! $ticketId) {
            return null;
        }

        return Ticket::with([
            'customer',
            'clientMachine',
            'assignee',
            'product',
            'stockMove.product',
            'quotationRequest',
            'messages.sender',
            'notes.admin',
        ])->find($ticketId);
    }
}
