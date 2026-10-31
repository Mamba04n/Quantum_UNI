<?php

namespace App\Livewire\Finanzas;

use App\Models\AccountPayable;
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
class AccountPayableDashboard extends Component
{
    use WithFileUploads, WithPagination;

    public $search = '';

    public $filterStatus = '';

    public bool $showForm = false;

    public $entity_id = '';

    public $new_entity_name = '';

    public $new_entity_type = 'socio';

    public $invoice_number = '';

    public $concept = '';

    public $classification = 'Compra de piezas';

    public $condition = '30 Dias';

    public $invoice_date = '';

    public $total_amount = '';

    public $amount_usd = '';

    public $due_date = '';

    public $support_document;

    public $editing_payable_id = null;

    public $editing_payable_lock_version = null;

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
        $payablesQuery = AccountPayable::with([
            'entity.bankAccounts',
            'supplier.bankAccounts',
            'purchaseOrder.supplier.bankAccounts',
            'purchaseOrderByReference.supplier.bankAccounts',
            'payments',
            'pendingChangeRequest.requester',
        ])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($query) {
                $search = "%{$this->search}%";

                $query->where(function ($query) use ($search) {
                    $query->where('payable_code', 'like', $search)
                        ->orWhere('invoice_number', 'like', $search)
                        ->orWhere('concept', 'like', $search)
                        ->orWhereHas('entity', fn ($entity) => $entity->where('name', 'like', $search))
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $search))
                        ->orWhereHas('purchaseOrder', fn ($order) => $order->where('reference_number', 'like', $search))
                        ->orWhereHas('purchaseOrderByReference', fn ($order) => $order->where('reference_number', 'like', $search))
                        ->orWhereHas('purchaseOrder.supplier', fn ($supplier) => $supplier->where('name', 'like', $search));
                });
            })
            ->latest();

        $filteredPayables = (clone $payablesQuery)->get();
        $payables = $payablesQuery->paginate(15);

        $totalPendiente = $filteredPayables->whereNotIn('status', ['pagado'])->sum(fn ($p) => $p->total_amount - $p->paid_amount);
        $totalVencido = $filteredPayables->where('status', '!=', 'pagado')
            ->filter(fn ($p) => $p->due_date && Carbon::parse($p->due_date)->isPast())
            ->sum(fn ($p) => $p->total_amount - $p->paid_amount);

        $entities = Entity::whereIn('type', ['socio', 'proveedor'])->orderBy('name')->get();

        $nextPayableCode = app(DocumentSequenceService::class)->formattedPeek('account_payable', 'CXP-', 6);

        return view('livewire.finanzas.account-payable-dashboard', compact('payables', 'entities', 'totalPendiente', 'totalVencido', 'nextPayableCode'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function togglePayableForm(): void
    {
        if ($this->showForm) {
            $this->cancelPayableEdit();

            return;
        }

        $this->showForm = true;
    }

    public function savePayable(FinanceDocumentService $documentService, FinanceChangeRequestService $changeRequestService, FinanceService $financeService): void
    {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $editingPayable = $this->editing_payable_id
            ? AccountPayable::query()->findOrFail($this->editing_payable_id)
            : null;

        $rules = [
            'entity_id' => ['required'],
            'concept' => ['required', 'string'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['required', 'date'],
            'support_document' => [$editingPayable ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ];
        $this->validate($rules);

        if ($editingPayable) {
            // Si ya viene con justificación (e.g. desde test o modal confirmado), procede a registrar la solicitud
            if (filled($this->change_justification)) {
                $this->confirmSavePayable($documentService, $changeRequestService, $financeService);

                return;
            }

            // Calcular resumen de cambios para el modal de aprobación
            $this->pending_changes = $this->calculatePendingChanges($editingPayable);
            $this->showApprovalModal = true;

            return;
        }

        // Creación manual directa
        $finalEntityId = $this->entity_id;
        if ($this->entity_id === 'new') {
            $this->validate(['new_entity_name' => ['required', 'string', 'max:255']]);
            $entity = Entity::create(['name' => $this->new_entity_name, 'type' => $this->new_entity_type]);
            $finalEntityId = $entity->id;
        }

        $partyName = $finalEntityId ? Entity::find($finalEntityId)?->name : $this->new_entity_name;
        $path = $documentService->store($this->support_document, 'cxp', $this->invoice_number ?: 'CXP-'.now()->format('YmdHis'), $partyName);

        $financeService->createPayable([
            'entity_id' => $finalEntityId,
            'supplier_id' => $finalEntityId,
            'invoice_number' => $this->invoice_number ?: null,
            'concept' => $this->concept,
            'classification' => $this->classification,
            'condition' => $this->condition,
            'invoice_date' => $this->invoice_date ?: null,
            'total_amount' => $this->total_amount,
            'amount_usd' => $this->amount_usd ?: null,
            'due_date' => $this->due_date,
            'support_document_path' => $path,
            'created_by' => auth()->id(),
        ]);

        $this->resetPayableForm();
        session()->flash('message', 'Cuenta por pagar registrada con soporte.');
    }

    public function confirmSavePayable(
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

        $editingPayable = AccountPayable::query()->findOrFail($this->editing_payable_id);

        $finalEntityId = $this->entity_id;
        if (! $editingPayable->purchase_order_id && $this->entity_id === 'new') {
            $this->validate(['new_entity_name' => ['required', 'string', 'max:255']]);
            $entity = Entity::create(['name' => $this->new_entity_name, 'type' => $this->new_entity_type]);
            $finalEntityId = $entity->id;
        }

        $partyName = $finalEntityId ? Entity::find($finalEntityId)?->name : $this->new_entity_name;
        $path = $editingPayable->support_document_path;
        if ($this->support_document) {
            $path = $documentService->store($this->support_document, 'cxp', $this->invoice_number ?: 'CXP-'.now()->format('YmdHis'), $partyName);
        }

        $data = [
            'entity_id' => $finalEntityId,
            'supplier_id' => $finalEntityId,
            'invoice_number' => $this->invoice_number ?: null,
            'concept' => $this->concept,
            'classification' => $this->classification,
            'condition' => $this->condition,
            'invoice_date' => $this->invoice_date ?: null,
            'total_amount' => $this->total_amount,
            'amount_usd' => $this->amount_usd ?: null,
            'due_date' => $this->due_date,
            'support_document_path' => $path,
        ];

        $changeRequestService->createRequest(
            FinanceChangeRequest::TYPE_CXP,
            $editingPayable->id,
            $data,
            $this->pending_changes ?: $this->calculatePendingChanges($editingPayable),
            $this->change_justification,
            $this->support_document ? $path : null
        );

        $this->showApprovalModal = false;
        $this->resetPayableForm();
        session()->flash('message', 'Cambio solicitado. La cuenta no se modifica hasta su aprobación.');
    }

    public function closeApprovalModal(): void
    {
        $this->showApprovalModal = false;
        $this->change_justification = '';
        $this->pending_changes = [];
    }

    public function editPayable(int $payableId): void
    {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $payable = AccountPayable::query()
            ->with(['purchaseOrder', 'supplier', 'entity'])
            ->findOrFail($payableId);

        $this->resetValidation();
        $this->showForm = true;
        $this->dispatch('scroll-to-finance-form', target: 'cxp-form');
        $this->editing_payable_id = $payable->id;
        $this->editing_payable_lock_version = (int) $payable->lock_version;
        $this->editing_has_order = (bool) $payable->purchase_order_id;
        $this->editing_origin = $payable->purchase_order_id
            ? trim(($payable->purchaseOrder?->reference_number ?: 'Orden de compra').' · '.($payable->resolvedSupplier()?->name ?: 'Proveedor'))
            : 'Sin orden de compra vinculada';
        $this->entity_id = (string) ($payable->supplier_id ?? $payable->entity_id);
        $this->new_entity_name = '';
        $this->invoice_number = $payable->invoice_number ?: '';
        $this->concept = $payable->concept ?: '';
        $this->classification = $payable->classification ?: 'Compra de piezas';
        $this->condition = $payable->condition ?: '30 Dias';
        $this->invoice_date = $payable->invoice_date?->format('Y-m-d') ?: '';
        $this->total_amount = $payable->total_amount;
        $this->amount_usd = $payable->amount_usd ?: '';
        $this->due_date = $payable->due_date?->format('Y-m-d') ?: '';
        $this->support_document = null;
        $this->change_justification = '';
        $this->showApprovalModal = false;
    }

    public function cancelPayableEdit(): void
    {
        $this->resetPayableForm();
        $this->resetValidation();
    }

    private function calculatePendingChanges(AccountPayable $payable): array
    {
        $changes = [];

        if ((float) $payable->total_amount !== (float) $this->total_amount) {
            $changes[] = [
                'field' => 'Importe Total C$',
                'before' => 'C$ ' . number_format((float) $payable->total_amount, 2),
                'after' => 'C$ ' . number_format((float) $this->total_amount, 2),
            ];
        }

        if ((float) ($payable->amount_usd ?? 0) !== (float) ($this->amount_usd ?: 0)) {
            $changes[] = [
                'field' => 'Importe USD',
                'before' => $payable->amount_usd ? '$ ' . number_format((float) $payable->amount_usd, 2) : '—',
                'after' => $this->amount_usd ? '$ ' . number_format((float) $this->amount_usd, 2) : '—',
            ];
        }

        $currentDueDate = $payable->due_date?->format('Y-m-d');
        if ($currentDueDate !== $this->due_date) {
            $changes[] = [
                'field' => 'Fecha de Vencimiento',
                'before' => $currentDueDate ? Carbon::parse($currentDueDate)->format('d/m/Y') : '—',
                'after' => $this->due_date ? Carbon::parse($this->due_date)->format('d/m/Y') : '—',
            ];
        }

        if (trim((string) $payable->invoice_number) !== trim((string) $this->invoice_number)) {
            $changes[] = [
                'field' => 'N° Factura',
                'before' => $payable->invoice_number ?: '—',
                'after' => $this->invoice_number ?: '—',
            ];
        }

        if (trim((string) $payable->concept) !== trim((string) $this->concept)) {
            $changes[] = [
                'field' => 'Concepto',
                'before' => $payable->concept ?: '—',
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

    private function resetPayableForm(): void
    {
        $this->reset([
            'invoice_number',
            'concept',
            'total_amount',
            'amount_usd',
            'entity_id',
            'new_entity_name',
            'support_document',
            'editing_payable_id',
            'editing_payable_lock_version',
            'editing_has_order',
            'editing_origin',
            'showApprovalModal',
            'change_justification',
            'pending_changes',
            'showForm',
        ]);
        $this->classification = 'Compra de piezas';
        $this->condition = '30 Dias';
        $this->invoice_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
    }

    public function openPaymentModal($payableId): void
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment'), 403);

        $payable = AccountPayable::findOrFail($payableId);
        $this->payment_payable_id = $payableId;
        $this->payment_currency = 'NIO';
        $this->payment_exchange_rate = $this->accountExchangeRate($payable);
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

        Log::info('Solicitud de pago CXP recibida desde Livewire.', [
            'account_payable_id' => $this->payment_payable_id,
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
            $payable = AccountPayable::findOrFail($this->payment_payable_id);
            $exchangeRate = $this->payment_currency === 'USD'
                ? app(\App\Services\ExchangeRateService::class)->rateToNio('USD')['rate']
                : 1;
            $paymentRegistrationService->register('cxp', $payable->id, $this->payment_evidence, $payable->entity?->name, [
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
            $this->addError('payment_form', 'No fue posible aplicar el pago. Intenta nuevamente; si persiste, revisa el registro del sistema.');

            return;
        }

        $this->reset(['payment_amount', 'payment_reference', 'payment_evidence', 'showPaymentModal', 'payment_payable_id', 'payment_exchange_rate']);
        $this->payment_currency = 'NIO';
        $this->payment_idempotency_key = (string) Str::ulid();
        session()->flash('message', 'Pago registrado con evidencia.');
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

    private function accountExchangeRate(?AccountPayable $payable): ?float
    {
        if (! $payable) {
            return null;
        }

        $storedRate = (float) ($payable->exchange_rate ?? 0);
        if ($storedRate > 0) {
            return $storedRate;
        }

        if ((float) $payable->amount_usd > 0 && (float) $payable->total_amount > 0) {
            return round((float) $payable->total_amount / (float) $payable->amount_usd, 6);
        }

        return null;
    }
}
