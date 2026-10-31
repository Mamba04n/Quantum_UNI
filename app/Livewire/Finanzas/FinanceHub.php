<?php

namespace App\Livewire\Finanzas;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\Payment;
use App\Services\ExchangeRateService;
use App\Services\FinanceDocumentService;
use App\Services\PaymentRegistrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class FinanceHub extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'resumen';

    public string $search = '';

    // Modal Cobro CXC
    public bool $showCxcModal = false;
    public ?int $selectedCxcId = null;
    public $cxc_amount = '';
    public string $cxc_currency = 'NIO';
    public string $cxc_method = 'Transferencia';
    public string $cxc_date = '';
    public string $cxc_reference = '';
    public $cxc_evidence = null;

    // Modal Pago CXP
    public bool $showCxpModal = false;
    public ?int $selectedCxpId = null;
    public $cxp_amount = '';
    public string $cxp_currency = 'NIO';
    public string $cxp_method = 'Transferencia';
    public string $cxp_date = '';
    public string $cxp_reference = '';
    public $cxp_evidence = null;

    // Modal Ingreso Directo
    public bool $showDirectIncomeModal = false;
    public string $direct_income_concept = '';
    public $direct_income_amount = '';
    public string $direct_income_currency = 'NIO';
    public string $direct_income_method = 'Transferencia';
    public string $direct_income_date = '';
    public string $direct_income_reference = '';
    public string $direct_income_party = '';
    public $direct_income_evidence = null;

    // Modal Egreso Directo (Gasto)
    public bool $showDirectExpenseModal = false;
    public string $direct_expense_concept = '';
    public string $direct_expense_category = 'Gasto operativo';
    public $direct_expense_amount = '';
    public string $direct_expense_currency = 'NIO';
    public string $direct_expense_method = 'Transferencia';
    public string $direct_expense_date = '';
    public string $direct_expense_reference = '';
    public string $direct_expense_party = '';
    public $direct_expense_evidence = null;

    public function mount(): void
    {
        $today = now()->toDateString();
        $this->cxc_date = $today;
        $this->cxp_date = $today;
        $this->direct_income_date = $today;
        $this->direct_expense_date = $today;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function openCxcModal(int $id): void
    {
        $receivable = AccountReceivable::findOrFail($id);
        $this->selectedCxcId = $receivable->id;
        $this->cxc_amount = max(0, (float) $receivable->total_amount - (float) $receivable->paid_amount);
        $this->cxc_currency = $receivable->currency ?: 'NIO';
        $this->cxc_date = now()->toDateString();
        $this->cxc_reference = '';
        $this->cxc_evidence = null;
        $this->showCxcModal = true;
    }

    public function openCxpModal(int $id): void
    {
        $payable = AccountPayable::findOrFail($id);
        $this->selectedCxpId = $payable->id;
        $this->cxp_amount = max(0, (float) $payable->total_amount - (float) $payable->paid_amount);
        $this->cxp_currency = $payable->currency ?: 'NIO';
        $this->cxp_date = now()->toDateString();
        $this->cxp_reference = '';
        $this->cxp_evidence = null;
        $this->showCxpModal = true;
    }

    public function saveCxcPayment(PaymentRegistrationService $service): void
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment') || auth()->user()?->can('finance.view'), 403);

        $this->validate([
            'selectedCxcId' => ['required', 'exists:account_receivables,id'],
            'cxc_amount' => ['required', 'numeric', 'min:0.01'],
            'cxc_currency' => ['required', 'in:NIO,USD'],
            'cxc_method' => ['required', 'string'],
            'cxc_date' => ['required', 'date'],
            'cxc_reference' => ['required', 'string', 'max:120'],
            'cxc_evidence' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ]);

        try {
            $receivable = AccountReceivable::with('entity')->findOrFail($this->selectedCxcId);
            $exchangeRate = $this->cxc_currency === 'USD'
                ? app(ExchangeRateService::class)->rateToNio('USD')['rate']
                : 1;

            $service->register('cxc', $receivable->id, $this->cxc_evidence, $receivable->entity?->name, [
                'amount' => $this->cxc_amount,
                'currency' => $this->cxc_currency,
                'exchange_rate' => $exchangeRate,
                'date' => $this->cxc_date,
                'method' => $this->cxc_method,
                'reference' => $this->cxc_reference,
                'registered_by' => auth()->id(),
                'idempotency_key' => (string) Str::ulid(),
            ]);

            session()->flash('message', 'Cobro registrado.');
            $this->showCxcModal = false;
            $this->reset(['selectedCxcId', 'cxc_amount', 'cxc_reference', 'cxc_evidence']);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo registrar el cobro. Revisa el historial antes de repetirlo.'));
        }
    }

    public function saveCxpPayment(PaymentRegistrationService $service): void
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment') || auth()->user()?->can('finance.view'), 403);

        $this->validate([
            'selectedCxpId' => ['required', 'exists:account_payables,id'],
            'cxp_amount' => ['required', 'numeric', 'min:0.01'],
            'cxp_currency' => ['required', 'in:NIO,USD'],
            'cxp_method' => ['required', 'string'],
            'cxp_date' => ['required', 'date'],
            'cxp_reference' => ['required', 'string', 'max:120'],
            'cxp_evidence' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ]);

        try {
            $payable = AccountPayable::with('entity')->findOrFail($this->selectedCxpId);
            $exchangeRate = $this->cxp_currency === 'USD'
                ? app(ExchangeRateService::class)->rateToNio('USD')['rate']
                : 1;

            $service->register('cxp', $payable->id, $this->cxp_evidence, $payable->entity?->name, [
                'amount' => $this->cxp_amount,
                'currency' => $this->cxp_currency,
                'exchange_rate' => $exchangeRate,
                'date' => $this->cxp_date,
                'method' => $this->cxp_method,
                'reference' => $this->cxp_reference,
                'registered_by' => auth()->id(),
                'idempotency_key' => (string) Str::ulid(),
            ]);

            session()->flash('message', 'Pago a proveedor registrado.');
            $this->showCxpModal = false;
            $this->reset(['selectedCxpId', 'cxp_amount', 'cxp_reference', 'cxp_evidence']);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo registrar el pago. Revisa el historial antes de repetirlo.'));
        }
    }

    public function saveDirectIncome(FinanceDocumentService $documentService): void
    {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $this->validate([
            'direct_income_concept' => ['required', 'string', 'max:255'],
            'direct_income_amount' => ['required', 'numeric', 'min:0.01'],
            'direct_income_currency' => ['required', 'in:NIO,USD'],
            'direct_income_method' => ['required', 'string'],
            'direct_income_date' => ['required', 'date'],
            'direct_income_reference' => ['required', 'string', 'max:120'],
            'direct_income_party' => ['nullable', 'string', 'max:150'],
            'direct_income_evidence' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ]);

        try {
            $evidencePath = $this->direct_income_evidence
                ? $documentService->store($this->direct_income_evidence, 'incomes', $this->direct_income_reference, $this->direct_income_party ?: 'ingreso-directo')
                : null;

            $exchangeRate = $this->direct_income_currency === 'USD'
                ? app(ExchangeRateService::class)->rateToNio('USD')['rate']
                : 1;

            $convertedNio = $this->direct_income_currency === 'USD'
                ? round((float) $this->direct_income_amount * (float) $exchangeRate, 2)
                : (float) $this->direct_income_amount;

            $referenceFingerprint = \App\Domain\Finanzas\ValueObjects\PaymentRequestFingerprint::reference('customer_payment', $this->direct_income_reference);

            Payment::create([
                'payment_code' => 'ING-'.Str::upper(Str::random(8)),
                'payment_type' => 'customer_payment',
                'payable_type' => Entity::class,
                'payable_id' => 0,
                'amount' => $convertedNio,
                'amount_original' => (float) $this->direct_income_amount,
                'currency' => $this->direct_income_currency,
                'exchange_rate' => $exchangeRate,
                'payment_date' => $this->direct_income_date,
                'payment_method' => $this->direct_income_method,
                'payment_reference' => $this->direct_income_reference,
                'receipt_reference' => $this->direct_income_reference,
                'reference_fingerprint' => $referenceFingerprint,
                'payment_evidence_path' => $evidencePath,
                'registered_by' => auth()->id(),
                'notes' => $this->direct_income_concept.($this->direct_income_party ? ' — Cliente/Origen: '.$this->direct_income_party : ''),
            ]);

            session()->flash('message', 'Ingreso registrado.');
            $this->showDirectIncomeModal = false;
            $this->reset(['direct_income_concept', 'direct_income_amount', 'direct_income_reference', 'direct_income_party', 'direct_income_evidence']);
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo registrar el ingreso. Revisa los datos e intenta de nuevo.'));
        }
    }

    public function saveDirectExpense(FinanceDocumentService $documentService): void
    {
        abort_unless(auth()->user()?->can('finance.view'), 403);

        $this->validate([
            'direct_expense_concept' => ['required', 'string', 'max:255'],
            'direct_expense_category' => ['required', 'string', 'max:80'],
            'direct_expense_amount' => ['required', 'numeric', 'min:0.01'],
            'direct_expense_currency' => ['required', 'in:NIO,USD'],
            'direct_expense_method' => ['required', 'string'],
            'direct_expense_date' => ['required', 'date'],
            'direct_expense_reference' => ['required', 'string', 'max:120'],
            'direct_expense_party' => ['nullable', 'string', 'max:150'],
            'direct_expense_evidence' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,docx'],
        ]);

        try {
            $evidencePath = $this->direct_expense_evidence
                ? $documentService->store($this->direct_expense_evidence, 'expenses', $this->direct_expense_reference, $this->direct_expense_party ?: 'egreso-directo')
                : null;

            $exchangeRate = $this->direct_expense_currency === 'USD'
                ? app(ExchangeRateService::class)->rateToNio('USD')['rate']
                : 1;

            $convertedNio = $this->direct_expense_currency === 'USD'
                ? round((float) $this->direct_expense_amount * (float) $exchangeRate, 2)
                : (float) $this->direct_expense_amount;

            $referenceFingerprint = \App\Domain\Finanzas\ValueObjects\PaymentRequestFingerprint::reference('supplier_payment', $this->direct_expense_reference);

            Payment::create([
                'payment_code' => 'EGR-'.Str::upper(Str::random(8)),
                'payment_type' => 'supplier_payment',
                'payable_type' => Entity::class,
                'payable_id' => 0,
                'amount' => $convertedNio,
                'amount_original' => (float) $this->direct_expense_amount,
                'currency' => $this->direct_expense_currency,
                'exchange_rate' => $exchangeRate,
                'payment_date' => $this->direct_expense_date,
                'payment_method' => $this->direct_expense_method,
                'payment_reference' => $this->direct_expense_reference,
                'receipt_reference' => $this->direct_expense_reference,
                'reference_fingerprint' => $referenceFingerprint,
                'payment_evidence_path' => $evidencePath,
                'registered_by' => auth()->id(),
                'notes' => '['.$this->direct_expense_category.'] '.$this->direct_expense_concept.($this->direct_expense_party ? ' — Beneficiario: '.$this->direct_expense_party : ''),
            ]);

            session()->flash('message', 'Gasto registrado.');
            $this->showDirectExpenseModal = false;
            $this->reset(['direct_expense_concept', 'direct_expense_amount', 'direct_expense_reference', 'direct_expense_party', 'direct_expense_evidence']);
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo registrar el gasto. Revisa los datos e intenta de nuevo.'));
        }
    }

    public function render()
    {
        // 1. Métricas consolidadas (Treinta)
        $totalIngresos = (float) Payment::query()
            ->where(fn ($q) => $q->whereNotNull('accounts_receivable_id')->orWhere('payment_type', 'customer_payment'))
            ->sum('amount');

        $totalEgresos = (float) Payment::query()
            ->where(fn ($q) => $q->whereNotNull('accounts_payable_id')->orWhere('payment_type', 'supplier_payment'))
            ->sum('amount');

        $balanceNeto = $totalIngresos - $totalEgresos;

        $totalPorCobrar = (float) AccountReceivable::query()
            ->whereNotIn('status', ['pagado'])
            ->selectRaw('SUM(total_amount - paid_amount) as saldo')
            ->value('saldo') ?? 0;

        $totalPorPagar = (float) AccountPayable::query()
            ->whereNotIn('status', ['pagado'])
            ->selectRaw('SUM(total_amount - paid_amount) as saldo')
            ->value('saldo') ?? 0;

        // 2. Consultas según pestaña activa
        $search = trim($this->search);

        $incomes = null;
        $expenses = null;
        $receivables = null;
        $payables = null;

        if ($this->tab === 'ingresos' || $this->tab === 'resumen') {
            $incomesQuery = Payment::query()
                ->with(['accountReceivable.entity', 'registrar'])
                ->where(fn ($q) => $q->whereNotNull('accounts_receivable_id')->orWhere('payment_type', 'customer_payment'))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('payment_code', 'like', "%{$search}%")
                            ->orWhere('payment_reference', 'like', "%{$search}%")
                            ->orWhere('notes', 'like', "%{$search}%")
                            ->orWhereHas('accountReceivable.entity', fn ($e) => $e->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('payment_date')
                ->orderByDesc('id');

            $incomes = $this->tab === 'resumen' ? $incomesQuery->limit(8)->get() : $incomesQuery->paginate(15);
        }

        if ($this->tab === 'egresos' || $this->tab === 'resumen') {
            $expensesQuery = Payment::query()
                ->with(['accountPayable.entity', 'registrar'])
                ->where(fn ($q) => $q->whereNotNull('accounts_payable_id')->orWhere('payment_type', 'supplier_payment'))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('payment_code', 'like', "%{$search}%")
                            ->orWhere('payment_reference', 'like', "%{$search}%")
                            ->orWhere('notes', 'like', "%{$search}%")
                            ->orWhereHas('accountPayable.entity', fn ($e) => $e->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('payment_date')
                ->orderByDesc('id');

            $expenses = $this->tab === 'resumen' ? $expensesQuery->limit(8)->get() : $expensesQuery->paginate(15);
        }

        if ($this->tab === 'cxc') {
            $receivables = AccountReceivable::query()
                ->with(['entity', 'saleOrder'])
                ->when($search, function ($q) use ($search) {
                    $q->where('receivable_code', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhere('concept', 'like', "%{$search}%")
                        ->orWhereHas('entity', fn ($e) => $e->where('name', 'like', "%{$search}%"));
                })
                ->orderByDesc('id')
                ->paginate(15);
        }

        if ($this->tab === 'cxp') {
            $payables = AccountPayable::query()
                ->with(['entity', 'supplier', 'purchaseOrder'])
                ->when($search, function ($q) use ($search) {
                    $q->where('payable_code', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhere('concept', 'like', "%{$search}%")
                        ->orWhereHas('entity', fn ($e) => $e->where('name', 'like', "%{$search}%"));
                })
                ->orderByDesc('id')
                ->paginate(15);
        }

        return view('livewire.finanzas.finance-hub', [
            'totalIngresos' => $totalIngresos,
            'totalEgresos' => $totalEgresos,
            'balanceNeto' => $balanceNeto,
            'totalPorCobrar' => $totalPorCobrar,
            'totalPorPagar' => $totalPorPagar,
            'incomes' => $incomes,
            'expenses' => $expenses,
            'receivables' => $receivables,
            'payables' => $payables,
        ]);
    }
}
