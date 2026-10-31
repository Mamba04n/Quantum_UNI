<?php

namespace App\Livewire\Clientes;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class TicketCenter extends Component
{
    use WithFileUploads;

    public bool $embedded = false;

    public string $view = 'list';
    public ?int $selectedTicketId = null;

    public string $type = Ticket::TYPE_SUPPORT;
    public string $priority = Ticket::PRIORITY_MEDIUM;
    public string $subject = '';
    public string $description = '';
    public $attachment;

    public string $machinery = '';
    public string $machineBrand = '';
    public string $machineModel = '';
    public string $equipmentSerial = '';
    public string $partCode = '';
    public int $requestQuantity = 1;

    public string $replyMessage = '';
    public $replyAttachment;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
    }

    protected function rules(): array
    {
        $rules = [
            'type' => ['required', 'in:' . implode(',', array_keys(Ticket::$typeLabels))],
            'priority' => ['required', 'in:' . implode(',', array_keys(Ticket::$priorityLabels))],
            'subject' => ['required', 'string', 'min:4', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ];

        if ($this->type === Ticket::TYPE_PURCHASE) {
            $rules = array_merge($rules, [
                'machinery' => ['required', 'string', 'max:255'],
                'machineBrand' => ['nullable', 'string', 'max:255'],
                'machineModel' => ['nullable', 'string', 'max:255'],
                'equipmentSerial' => ['nullable', 'string', 'max:255'],
                'partCode' => ['required', 'string', 'max:255'],
                'requestQuantity' => ['required', 'integer', 'min:1', 'max:999999'],
            ]);
        }

        return $rules;
    }

    public function createTicket(TicketService $service): void
    {
        $this->validate();

        $purchasePayload = $this->type === Ticket::TYPE_PURCHASE
            ? [
                'machinery' => trim($this->machinery),
                'machine_brand' => trim($this->machineBrand) ?: null,
                'machine_model' => trim($this->machineModel) ?: null,
                'equipment_serial' => trim($this->equipmentSerial) ?: null,
                'part_code' => trim($this->partCode),
                'request_quantity' => $this->requestQuantity,
                'request_urgency' => $this->priority,
            ]
            : [];

        $ticket = $service->createTicket(array_merge([
            'type' => $this->type,
            'priority' => $this->priority,
            'subject' => $this->subject,
            'description' => $this->description,
        ], $purchasePayload), Auth::user(), $this->attachment);

        $this->resetForm();
        $this->openTicket($ticket->id);
        session()->flash('message', "Ticket {$ticket->code} creado.");
    }

    public function openTicket(int $ticketId): void
    {
        $ticket = Ticket::where('customer_id', Auth::id())->findOrFail($ticketId);
        app(TicketService::class)->markMessagesAsRead($ticket, 'customer');

        $this->selectedTicketId = $ticket->id;
        $this->view = 'detail';
        $this->resetValidation();
    }

    public function sendReply(TicketService $service): void
    {
        $this->validate([
            'replyMessage' => ['required', 'string', 'min:2', 'max:5000'],
            'replyAttachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        $ticket = Ticket::where('customer_id', Auth::id())->findOrFail($this->selectedTicketId);
        $service->addMessage($ticket, Auth::user(), $this->replyMessage, 'customer', $this->replyAttachment);

        $this->reset(['replyMessage', 'replyAttachment']);
        session()->flash('message', 'Respuesta enviada.');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->view = 'create';
    }

    public function backToList(): void
    {
        $this->view = 'list';
        $this->selectedTicketId = null;
    }

    private function resetForm(): void
    {
        $this->reset([
            'type',
            'priority',
            'subject',
            'description',
            'attachment',
            'machinery',
            'machineBrand',
            'machineModel',
            'equipmentSerial',
            'partCode',
            'requestQuantity',
            'replyMessage',
            'replyAttachment',
        ]);

        $this->type = Ticket::TYPE_SUPPORT;
        $this->priority = Ticket::PRIORITY_MEDIUM;
        $this->requestQuantity = 1;
        $this->resetValidation();
    }

    public function render()
    {
        $tickets = Ticket::with(['assignee', 'product'])
            ->where('customer_id', Auth::id())
            ->latest('last_message_at')
            ->latest()
            ->get();

        $selectedTicket = $this->selectedTicketId
            ? Ticket::with(['messages.sender', 'product', 'stockMove', 'quotationRequest', 'assignee'])
                ->where('customer_id', Auth::id())
                ->find($this->selectedTicketId)
            : null;

        if ($selectedTicket) {
            app(TicketService::class)->markMessagesAsRead($selectedTicket, 'customer');
        }

        return view('livewire.clientes.ticket-center', [
            'tickets' => $tickets,
            'selectedTicket' => $selectedTicket,
            'typeLabels' => Ticket::$typeLabels,
            'priorityLabels' => Ticket::$priorityLabels,
            'statusLabels' => Ticket::$statusLabels,
        ]);
    }
}
