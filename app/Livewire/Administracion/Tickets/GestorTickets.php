<?php

namespace App\Livewire\Administracion\Tickets;

use App\Domain\Clientes\Tickets\Queries\ListarAgentesTicketsQuery;
use App\Domain\Clientes\Tickets\Queries\ListarTicketsQuery;
use App\Domain\Clientes\Tickets\Queries\ObtenerComentariosTrackerTicketQuery;
use App\Domain\Clientes\Tickets\Queries\ObtenerTicketDetalleQuery;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GestorTickets extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterType = '';
    public string $filterAssignee = '';
    public int $perPage = 20;

    public ?int $selectedTicketId = null;
    public string $replyMessage = '';
    public $replyAttachment;
    public string $internalNote = '';
    public string $status = Ticket::STATUS_OPEN;
    public $assignedTo = null;

    public function updating($property): void
    {
        if (in_array($property, ['search', 'filterStatus', 'filterPriority', 'filterType', 'filterAssignee', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function selectTicket(int $ticketId): void
    {
        $ticket = Ticket::with(['assignee'])->findOrFail($ticketId);
        $this->authorize('view', $ticket);

        app(TicketService::class)->markMessagesAsRead($ticket, 'admin');

        $this->selectedTicketId = $ticket->id;
        $this->status = $ticket->status;
        $this->assignedTo = $ticket->assigned_to;
        $this->reset(['replyMessage', 'replyAttachment', 'internalNote']);
        $this->resetValidation();
    }

    public function sendReply(TicketService $service): void
    {
        $this->validate([
            'replyMessage' => ['required', 'string', 'min:2', 'max:5000'],
            'replyAttachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        $ticket = Ticket::findOrFail($this->selectedTicketId);
        $this->authorize('update', $ticket);

        if (!$ticket->assigned_to && Auth::check()) {
            $service->updateTicketState($ticket, ['assigned_to' => Auth::id()]);
            $this->assignedTo = Auth::id();
        }

        $service->addMessage($ticket, Auth::user(), $this->replyMessage, 'admin', $this->replyAttachment);
        $this->reset(['replyMessage', 'replyAttachment']);
        session()->flash('message', 'Respuesta enviada al cliente.');
    }

    public function saveState(TicketService $service): void
    {
        $this->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Ticket::$statusLabels))],
            'assignedTo' => ['nullable', 'exists:users,id'],
        ]);

        $ticket = Ticket::findOrFail($this->selectedTicketId);
        $this->authorize('update', $ticket);

        $assignedTo = $this->assignedTo;
        if (!$assignedTo && Auth::check()) {
            $assignedTo = Auth::id();
        }

        $service->updateTicketState($ticket, [
            'status' => $this->status,
            'assigned_to' => $assignedTo,
        ]);

        $this->assignedTo = $assignedTo;
        session()->flash('message', 'Ticket actualizado.');
    }

    public function addInternalNote(TicketService $service): void
    {
        $this->validate([
            'internalNote' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $ticket = Ticket::findOrFail($this->selectedTicketId);
        $this->authorize('update', $ticket);

        if (!$ticket->assigned_to && Auth::check()) {
            $service->updateTicketState($ticket, ['assigned_to' => Auth::id()]);
            $this->assignedTo = Auth::id();
        }

        $service->addInternalNote($ticket, Auth::user(), $this->internalNote);
        $this->reset('internalNote');
        session()->flash('message', 'Nota interna guardada.');
    }

    public function render()
    {
        $tickets = app(ListarTicketsQuery::class)->ejecutar([
            'search' => $this->search,
            'filterStatus' => $this->filterStatus,
            'filterPriority' => $this->filterPriority,
            'filterType' => $this->filterType,
            'filterAssignee' => $this->filterAssignee,
        ])
            ->simplePaginate($this->perPage);

        $selectedTicket = app(ObtenerTicketDetalleQuery::class)->ejecutar($this->selectedTicketId);

        if ($selectedTicket) {
            app(TicketService::class)->markMessagesAsRead($selectedTicket, 'admin');
        }

        $trackerComments = app(ObtenerComentariosTrackerTicketQuery::class)->ejecutar($selectedTicket);
        $agents = app(ListarAgentesTicketsQuery::class)->ejecutar();

        return view('livewire.administracion.tickets.gestor-tickets', [
            'tickets' => $tickets,
            'selectedTicket' => $selectedTicket,
            'agents' => $agents,
            'trackerComments' => $trackerComments,
            'typeLabels' => Ticket::$typeLabels,
            'priorityLabels' => Ticket::$priorityLabels,
            'statusLabels' => Ticket::$statusLabels,
        ]);
    }
}
