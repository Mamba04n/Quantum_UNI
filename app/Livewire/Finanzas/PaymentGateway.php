<?php

namespace App\Livewire\Finanzas;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Services\PaymentRegistrationService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class PaymentGateway extends Component
{
    use WithFileUploads;

    public $type = 'cxc';

    public $account_id = '';

    public $amount = 0;

    public $payment_method = 'Transferencia';

    public $reference = '';

    public $payment_date;

    public $payment_evidence;

    public $payment_idempotency_key = '';

    public function mount()
    {
        $this->payment_date = date('Y-m-d');
        $this->payment_idempotency_key = (string) Str::ulid();
    }

    public function processPayment(PaymentRegistrationService $paymentRegistrationService)
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment'), 403);

        $this->validate([
            'type' => 'required|in:cxc,cxp',
            'account_id' => 'required|numeric',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference' => 'required|string',
            'payment_date' => 'required|date',
            'payment_evidence' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,xlsx,docx',
        ]);

        try {
            $account = $this->type === 'cxc'
                ? AccountReceivable::with('entity')->findOrFail($this->account_id)
                : AccountPayable::with('entity')->findOrFail($this->account_id);
            $payment = $paymentRegistrationService->register(
                $this->type,
                (int) $this->account_id,
                $this->payment_evidence,
                $account->entity?->name,
                [
                    'amount' => $this->amount,
                    'method' => $this->payment_method,
                    'reference' => $this->reference,
                    'date' => $this->payment_date,
                    'registered_by' => auth()->id(),
                    'idempotency_key' => $this->payment_idempotency_key,
                ]
            );
            session()->flash('message', 'Pago registrado por C$'.number_format($payment->amount, 2));
            $this->reset(['account_id', 'amount', 'reference', 'payment_evidence']);
            $this->mount();
        } catch (\Exception $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo registrar el pago. Revisa el historial antes de repetirlo.'));
        }
    }

    public function render()
    {
        if ($this->type === 'cxc') {
            $pendingAccounts = AccountReceivable::with('entity')->whereIn('status', ['pendiente', 'parcial'])->get();
        } else {
            $pendingAccounts = AccountPayable::with('entity')->whereIn('status', ['pendiente', 'parcial'])->get();
        }

        return view('livewire.finanzas.payment-gateway', compact('pendingAccounts'));
    }
}
