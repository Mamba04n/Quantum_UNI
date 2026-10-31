<?php

namespace App\Livewire\Administracion\Finanzas;

use App\Domain\Finanzas\Services\FinanceChangeRequestService;
use App\Models\FinanceChangeRequest;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestorRevisionFinanzas extends Component
{
    public ?int $selectedRequestId = null;

    public string $adminNotes = '';

    public string $typeFilter = '';

    public function selectRequest(?int $requestId = null): void
    {
        abort_unless(auth()->user()?->can('client_offer_review.view') || auth()->user()?->can('finance.view') || auth()->user()?->can('full_access') || auth()->user()?->hasRole('admin'), 403);

        $this->selectedRequestId = $requestId;
        $this->adminNotes = '';
        $this->resetValidation();
    }

    public function approve(FinanceChangeRequestService $service): void
    {
        abort_unless(auth()->user()?->can('client_offer_review.view') || auth()->user()?->can('finance.view') || auth()->user()?->can('full_access') || auth()->user()?->hasRole('admin'), 403);

        $request = $this->selectedRequest();
        if (! $request) {
            return;
        }

        try {
            $service->approve($request, auth()->user(), $this->adminNotes ?: null);
            $this->adminNotes = '';
            $this->selectedRequestId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Modificación autorizada y aplicada a la cuenta.']);
            session()->flash('message', 'Modificación autorizada y aplicada a la cuenta.');
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function reject(FinanceChangeRequestService $service): void
    {
        abort_unless(auth()->user()?->can('client_offer_review.view') || auth()->user()?->can('finance.view') || auth()->user()?->can('full_access') || auth()->user()?->hasRole('admin'), 403);

        $request = $this->selectedRequest();
        if (! $request) {
            return;
        }

        $this->validate([
            'adminNotes' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'adminNotes.required' => 'Debes ingresar el motivo del rechazo.',
            'adminNotes.min' => 'El motivo de rechazo debe contener al menos 3 caracteres.',
        ]);

        try {
            $service->reject($request, auth()->user(), $this->adminNotes);
            $this->adminNotes = '';
            $this->selectedRequestId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud de modificación rechazada.']);
            session()->flash('message', 'Solicitud de modificación rechazada.');
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('client_offer_review.view') || auth()->user()?->can('finance.view') || auth()->user()?->can('full_access') || auth()->user()?->hasRole('admin'), 403);

        $requests = FinanceChangeRequest::query()
            ->with([
                'requester',
                'accountPayable.entity',
                'accountPayable.supplier',
                'accountPayable.purchaseOrder',
                'accountReceivable.entity',
                'accountReceivable.saleOrder',
            ])
            ->where('status', FinanceChangeRequest::STATUS_PENDING)
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->latest()
            ->get();

        $selectedRequest = $this->selectedRequest();

        return view('livewire.administracion.finanzas.revision.gestor-revision-finanzas', [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest,
        ]);
    }

    private function selectedRequest(): ?FinanceChangeRequest
    {
        if (! $this->selectedRequestId) {
            return null;
        }

        return FinanceChangeRequest::query()
            ->with([
                'requester',
                'accountPayable.entity',
                'accountPayable.supplier',
                'accountPayable.purchaseOrder',
                'accountReceivable.entity',
                'accountReceivable.saleOrder',
            ])
            ->find($this->selectedRequestId);
    }
}
