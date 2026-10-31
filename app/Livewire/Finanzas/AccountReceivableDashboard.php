<?php

namespace App\Livewire\Finanzas;

use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Services\DocumentSequenceService;
use App\Services\FinanceDocumentService;
use App\Services\FinanceService;
use App\Services\PaymentRegistrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

use App\Domain\Finanzas\Services\FinanceChangeRequestService;
use App\Models\FinanceChangeRequest;

#[Layout('layouts.app')]
class AccountReceivableDashboard extends Component
{
    use WithFileUploads, WithPagination;

    public $search = '';

    public $filterStatus = '';

    public bool $showForm = false;

    public $entity_id = '';

    public $new_entity_name = '';

    public $new_entity_type = 'cliente';

    public $invoice_number = '';

    public $concept = '';

    public $classification = 'Venta de producto';

    public $condition = '30 Días';

    public $invoice_date = '';

    public $total_amount = '';

    public $due_date = '';

    public $support_document;

    public $editing_receivable_id = null;

    public $editing_receivable_lock_version = null;

    public bool $editing_has_order = false;

    public $editing_origin = '';

    public bool $showApprovalModal = false;

    public string $change_justification = '';

    public array $pending_changes = [];

    public $showPaymentModal = false;

    public $payment_payable_id = '';

    public $payment_amount = '';

    public $payment_currency = 'NIO';

    public $payment_exchange_rate = '';

    public $payment_method = 'Transferencia';

    public $payment_date = '';

    public $payment_reference = '';

    public $payment_evidence;

    public $payment_idempotency_key = '';

    public function mount(): void
    {
        $this->invoice_date = date('Y-m-d');
        $this->due_date = date('Y-m-d', strtotime('+30 days'));
        $this->payment_date = date('Y-m-d');
        $this->payment_idempotency_key = (string) Str::ulid();
    }

    public function render()
    {
        $receivablesQuery = AccountReceivable::with([
            'entity',
            'saleOrder',
            'saleOrderByReference',
            'quotationRequest',
            'payments',
            'pendingChangeRequest.requester',
        ])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($query) {
                $search = "%{$this->search}%";

                $query->where(function ($query) use ($search) {
                    $query->where('receivable_code', 'like', $search)
                        ->orWhere('invoice_number', 'like', $search)
                        ->orWhere('concept', 'like', $search)
                        ->orWhereHas('entity', fn ($entity) => $entity->where('name', 'like', $search))
                        ->orWhereHas('saleOrder', fn ($order) => $order->where('reference', 'like', $search))
                        ->orWhereHas('saleOrderByReference', fn ($order) => $order->where('reference', 'like', $search))
                        ->orWhereHas('quotationRequest', fn ($quotation) => $quotation->where('reference', 'like', $search));
                });
            })
            ->latest();

        $filteredReceivables = (clone $receivablesQuery)->get();
        $receivables = $receivablesQuery->paginate(15);

        $totalPendiente = $filteredReceivables->whereNotIn('status', ['pagado'])->sum(fn ($r) => $r->total_amount - $r->paid_amount);
        $totalVencido = $filteredReceivables->where('status', '!=', 'pagado')
            ->filter(fn ($r) => $r->due_date && Carbon::parse($r->due_date)->isPast())
            ->sum(fn ($r) => $r->total_amount - $r->paid_amount);

        $entities = Entity::where('type', 'cliente')->orderBy('name')->get();

        $nextReceivableCode = app(DocumentSequenceService::class)->formattedPeek('account_receivable', 'CXC-', 6);

        return view('livewire.finanzas.account-receivable-dashboard', compact('receivables', 'entities', 'totalPendiente', 'totalVencido', 'nextReceivableCode'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function toggleReceivableForm(): void
    {
        if ($this->showForm) {
            $this->cancelReceivableEdit();

            return;
        }

        $this->showForm = true;
    }

    public function saveReceivable(FinanceDocumentService $documentService, FinanceChangeRequestService $changeRequestService, FinanceService $financeService): void
    {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $editingReceivable = $this->editing_receivable_id
            ? AccountReceivable::query()->findOrFail($this->editing_receivable_id)
            : null;

        $rules = [
            'entity_id' => ['required'],
            'concept' => ['required', 'string'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['required', 'date'],
            'support_document' => [$editingReceivable ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ];
        $this->validate($rules);

        if ($editingReceivable) {
            // Si ya viene con justificación (e.g. desde modal confirmado o test), procede a registrar la solicitud
            if (filled($this->change_justification)) {
                $this->confirmSaveReceivable($documentService, $changeRequestService, $financeService);

                return;
            }

            // Calcular resumen de cambios para el modal de aprobación
            $this->pending_changes = $this->calculatePendingChanges($editingReceivable);
            $this->showApprovalModal = true;

            return;
        }

        // Registro manual directo
        $finalEntityId = $this->entity_id;
        if ($this->entity_id === 'new') {
            $this->validate(['new_entity_name' => ['required', 'string', 'max:255']]);
            $entity = Entity::create(['name' => $this->new_entity_name, 'type' => $this->new_entity_type]);
            $finalEntityId = $entity->id;
        }

        $partyName = $finalEntityId ? Entity::find($finalEntityId)?->name : $this->new_entity_name;
        $path = $documentService->store($this->support_document, 'cxc', $this->invoice_number ?: 'CXC-'.now()->format('YmdHis'), $partyName);

        $financeService->createReceivable([
            'entity_id' => $finalEntityId,
            'invoice_number' => $this->invoice_number ?: null,
            'concept' => $this->concept,
            'classification' => $this->classification,
            'condition' => $this->condition,
            'invoice_date' => $this->invoice_date ?: null,
            'total_amount' => $this->total_amount,
            'due_date' => $this->due_date,
            'support_document_path' => $path,
            'created_by' => auth()->id(),
        ]);

        $this->resetReceivableForm();
        session()->flash('message', 'Cuenta por cobrar manual registrada sin orden vinculada.');
    }

    public function confirmSaveReceivable(
        FinanceDocumentService $documentService,
        FinanceChangeRequestService $changeRequestService,
        FinanceService $financeService
    ): void {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $this->validate([
            'change_justification' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'change_justification.required' => 'El motivo o justificación de la modificación es obligatorio para datos sensibles.',
            'change_justification.min' => 'El motivo debe contener al menos 3 caracteres.',
        ]);

        $editingReceivable = AccountReceivable::query()->findOrFail($this->editing_receivable_id);

        $finalEntityId = $this->entity_id;
        if (! $editingReceivable->sales_order_id && $this->entity_id === 'new') {
            $this->validate(['new_entity_name' => ['required', 'string', 'max:255']]);
            $entity = Entity::create(['name' => $this->new_entity_name, 'type' => $this->new_entity_type]);
            $finalEntityId = $entity->id;
        }

        $path = $editingReceivable->support_document_path;
        if ($this->support_document) {
            $partyName = $finalEntityId ? Entity::find($finalEntityId)?->name : $this->new_entity_name;
            $path = $documentService->store($this->support_document, 'cxc', $this->invoice_number ?: 'CXC-'.now()->format('YmdHis'), $partyName);
        }

        $data = [
            'entity_id' => $finalEntityId,
            'invoice_number' => $this->invoice_number ?: null,
            'concept' => $this->concept,
            'classification' => $this->classification,
            'condition' => $this->condition,
            'invoice_date' => $this->invoice_date ?: null,
            'total_amount' => $this->total_amount,
            'due_date' => $this->due_date,
            'support_document_path' => $path,
        ];

        $changeRequestService->createRequest(
            FinanceChangeRequest::TYPE_CXC,
            $editingReceivable->id,
            $data,
            $this->pending_changes ?: $this->calculatePendingChanges($editingReceivable),
            $this->change_justification,
            $this->support_document ? $path : null
        );

        $this->showApprovalModal = false;
        $this->resetReceivableForm();
        session()->flash('message', 'Cambio solicitado. La cuenta no se modifica hasta su aprobación.');
    }

    public function closeApprovalModal(): void
    {
        $this->showApprovalModal = false;
        $this->change_justification = '';
        $this->pending_changes = [];
    }

    public function editReceivable(int $receivableId): void
    {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $receivable = AccountReceivable::query()
            ->with(['saleOrder', 'quotationRequest'])
            ->findOrFail($receivableId);

        $this->resetValidation();
        $this->showForm = true;
        $this->dispatch('scroll-to-finance-form', target: 'cxc-form');
        $this->editing_receivable_id = $receivable->id;
        $this->editing_receivable_lock_version = (int) $receivable->lock_version;
        $this->editing_has_order = (bool) $receivable->sales_order_id;
        $this->editing_origin = $receivable->sales_order_id
            ? trim(($receivable->saleOrder?->reference ?: 'Orden').' · '.($receivable->quotationRequest?->reference ?: 'Solicitud'))
            : 'Sin orden vinculada';
        $this->entity_id = (string) $receivable->entity_id;
        $this->new_entity_name = '';
        $this->invoice_number = $receivable->invoice_number ?: '';
        $this->concept = $receivable->concept ?: '';
        $this->classification = $receivable->classification ?: 'Venta de producto';
        $this->condition = $receivable->condition ?: 'Contado';
        $this->invoice_date = $receivable->invoice_date?->format('Y-m-d') ?: '';
        $this->total_amount = $receivable->total_amount;
        $this->due_date = $receivable->due_date?->format('Y-m-d') ?: '';
        $this->support_document = null;
        $this->change_justification = '';
        $this->showApprovalModal = false;
    }

    public function cancelReceivableEdit(): void
    {
        $this->resetReceivableForm();
        $this->resetValidation();
    }

    private function calculatePendingChanges(AccountReceivable $receivable): array
    {
        $changes = [];

        if ((float) $receivable->total_amount !== (float) $this->total_amount) {
            $changes[] = [
                'field' => 'Importe Total C$',
                'before' => 'C$ ' . number_format((float) $receivable->total_amount, 2),
                'after' => 'C$ ' . number_format((float) $this->total_amount, 2),
            ];
        }

        $currentDueDate = $receivable->due_date?->format('Y-m-d');
        if ($currentDueDate !== $this->due_date) {
            $changes[] = [
                'field' => 'Fecha de Vencimiento',
                'before' => $currentDueDate ? Carbon::parse($currentDueDate)->format('d/m/Y') : '—',
                'after' => $this->due_date ? Carbon::parse($this->due_date)->format('d/m/Y') : '—',
            ];
        }

        if (trim((string) $receivable->invoice_number) !== trim((string) $this->invoice_number)) {
            $changes[] = [
                'field' => 'N° Factura',
                'before' => $receivable->invoice_number ?: '—',
                'after' => $this->invoice_number ?: '—',
            ];
        }

        if (trim((string) $receivable->concept) !== trim((string) $this->concept)) {
            $changes[] = [
                'field' => 'Concepto',
                'before' => $receivable->concept ?: '—',
                'after' => $this->concept ?: '—',
            ];
        }

        if ($this->support_document) {
            $changes[] = [
                'field' => 'Documento de Soporte',
                'before' => 'Archivo existente',
                'after' => 'Nuevo archivo adjunto',
            ];
        }

        if (empty($changes)) {
            $changes[] = [
                'field' => 'Datos generales',
                'before' => 'Sin cambios numéricos',
                'after' => 'Actualización de metadatos',
            ];
        }

        return $changes;
    }

    public function openPaymentModal($receivableId): void
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment'), 403);

        $receivable = AccountReceivable::findOrFail($receivableId);
        $this->payment_payable_id = $receivableId;
        $this->payment_currency = 'NIO';
        $this->payment_exchange_rate = $this->accountExchangeRate($receivable);
        $this->payment_idempotency_key = (string) Str::ulid();
        $this->showPaymentModal = true;
    }

    public function updatedPaymentCurrency(): void
    {
        $this->payment_exchange_rate = $this->payment_currency === 'USD'
            ? (float) app(\App\Services\ExchangeRateService::class)->rateToNio('USD')['rate']
            : 1;
    }

    public function closeModal(): void
    {
        $this->showPaymentModal = false;
        $this->payment_payable_id = '';
    }

    public function savePayment(PaymentRegistrationService $paymentRegistrationService): void
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment'), 403);

        Log::info('Solicitud de abono CXC recibida desde Livewire.', [
            'account_receivable_id' => $this->payment_payable_id,
            'user_id' => auth()->id(),
        ]);

        $this->validate([
            'payment_amount' => ['required', 'numeric', 'min:1'],
            'payment_currency' => ['required', 'in:NIO,USD'],
            'payment_method' => ['required', 'string'],
            'payment_date' => ['required', 'date'],
            'payment_reference' => ['required', 'string', 'max:120'],
            'payment_evidence' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ]);

        try {
            $receivable = AccountReceivable::findOrFail($this->payment_payable_id);
            $exchangeRate = $this->payment_currency === 'USD'
                ? app(\App\Services\ExchangeRateService::class)->rateToNio('USD')['rate']
                : 1;
            $paymentRegistrationService->register('cxc', $receivable->id, $this->payment_evidence, $receivable->entity?->name, [
                'amount' => $this->payment_amount,
                'currency' => $this->payment_currency,
                'exchange_rate' => $exchangeRate,
                'date' => $this->payment_date,
                'method' => $this->payment_method,
                'reference' => $this->payment_reference,
                'registered_by' => auth()->id(),
                'idempotency_key' => $this->payment_idempotency_key,
            ]);
        } catch (ValidationException $exception) {
            $this->showPaymentErrors($exception);

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('payment_form', 'No fue posible registrar el abono. Intenta nuevamente; si persiste, revisa el registro del sistema.');

            return;
        }

        $this->reset(['payment_amount', 'payment_reference', 'payment_evidence', 'showPaymentModal', 'payment_payable_id', 'payment_exchange_rate']);
        $this->payment_currency = 'NIO';
        $this->payment_idempotency_key = (string) Str::ulid();
        session()->flash('message', 'Cobro registrado con evidencia.');
    }

    private function showPaymentErrors(ValidationException $exception): void
    {
        $fieldMap = [
            'amount' => 'payment_amount',
            'currency' => 'payment_currency',
            'exchange_rate' => 'payment_exchange_rate',
            'method' => 'payment_method',
            'reference' => 'payment_reference',
            'payment_evidence_path' => 'payment_evidence',
        ];

        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($fieldMap[$field] ?? $field, $message);
            }
        }
    }

    private function accountExchangeRate(?AccountReceivable $receivable): ?float
    {
        if (! $receivable) {
            return null;
        }

        $storedRate = (float) ($receivable->exchange_rate ?? 0);
        if ($storedRate > 0) {
            return $storedRate;
        }

        if ((float) $receivable->amount_usd > 0 && (float) $receivable->total_amount > 0) {
            return round((float) $receivable->total_amount / (float) $receivable->amount_usd, 6);
        }

        return null;
    }

    private function resetReceivableForm(): void
    {
        $this->reset([
            'invoice_number',
            'concept',
            'total_amount',
            'entity_id',
            'new_entity_name',
            'support_document',
            'editing_receivable_id',
            'editing_receivable_lock_version',
            'editing_has_order',
            'editing_origin',
            'showApprovalModal',
            'change_justification',
            'pending_changes',
            'showForm',
        ]);
        $this->classification = 'Venta de producto';
        $this->condition = '30 Días';
        $this->invoice_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
    }
}
