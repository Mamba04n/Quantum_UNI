<?php

namespace App\Livewire\Finanzas;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateChangeRequest;
use App\Models\Currency;
use App\Services\ExchangeRateService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ExchangeRateManager extends Component
{
    public string $baseCurrency = 'USD';

    public string $quoteCurrency = 'NIO';

    public string $rate = '';

    public string $rateDate = '';

    public string $newCurrencyCode = '';

    public string $newCurrencyName = '';

    public string $newCurrencySymbol = '';

    public bool $newCurrencyAutomatic = false;

    public string $changeReason = '';

    public function mount(): void
    {
        $this->rateDate = now()->toDateString();
        $this->loadCurrentRate();
    }

    public function updatedBaseCurrency(): void
    {
        $this->loadCurrentRate();
    }

    public function saveRate(ExchangeRateService $service): void
    {
        $validated = $this->validate([
            'baseCurrency' => ['required', 'exists:currencies,code'],
            'quoteCurrency' => ['required', 'in:NIO'],
            'rate' => ['required', 'decimal:0,6', 'gt:0'],
            'rateDate' => ['required', 'date', 'before_or_equal:today'],
            'changeReason' => [$this->canAdminister() ? 'nullable' : 'required', 'string', 'max:1000'],
        ]);

        if (! $this->canAdminister()) {
            ExchangeRateChangeRequest::create([
                'base_currency' => $validated['baseCurrency'],
                'quote_currency' => $validated['quoteCurrency'],
                'proposed_rate' => $validated['rate'],
                'rate_date' => $validated['rateDate'],
                'reason' => trim($validated['changeReason']),
                'status' => ExchangeRateChangeRequest::STATUS_PENDING,
                'requested_by' => auth()->id(),
            ]);
            $this->changeReason = '';
            session()->flash('message', 'Cambio solicitado. La tasa vigente no se ha modificado.');

            return;
        }

        $service->approveManualRate(
            $validated['baseCurrency'],
            $validated['quoteCurrency'],
            $validated['rate'],
            $validated['rateDate'],
            auth()->user(),
        );

        $this->rate = '';
        session()->flash('message', 'Tasa manual registrada.');
        $this->loadCurrentRate();
    }

    public function refreshAutomaticRate(ExchangeRateService $service): void
    {
        $this->authorizeAdministration();

        $currency = Currency::query()->where('code', $this->baseCurrency)->firstOrFail();
        if (! $currency->automatic_updates_enabled) {
            $this->addError('baseCurrency', 'La actualización automática no está habilitada para esta moneda.');

            return;
        }

        try {
            $service->refreshAutomaticRate($this->baseCurrency);
            $this->loadCurrentRate();
            session()->flash('message', "Tasa {$this->baseCurrency} → NIO actualizada desde la fuente automática.");
        } catch (ValidationException $exception) {
            $this->addError('rate', collect($exception->errors())->flatten()->first());
        }
    }

    public function toggleAutomatic(int $currencyId): void
    {
        $this->authorizeAdministration();
        $currency = Currency::findOrFail($currencyId);
        abort_if($currency->code === 'NIO' || $currency->is_fixed, 422);
        $currency->update(['automatic_updates_enabled' => ! $currency->automatic_updates_enabled]);
    }

    public function setAutomaticInterval(int $currencyId, int $hours): void
    {
        $this->authorizeAdministration();
        abort_unless(in_array($hours, [24, 48], true), 422);
        $currency = Currency::findOrFail($currencyId);
        abort_if($currency->code === 'NIO' || $currency->is_fixed, 422);
        $currency->update(['automatic_update_interval_hours' => $hours]);
    }

    public function addCurrency(): void
    {
        $this->authorizeAdministration();
        $validated = $this->validate([
            'newCurrencyCode' => ['required', 'string', 'size:3', 'alpha', 'unique:currencies,code'],
            'newCurrencyName' => ['required', 'string', 'max:100'],
            'newCurrencySymbol' => ['required', 'string', 'max:10'],
            'newCurrencyAutomatic' => ['boolean'],
        ]);

        $currency = Currency::create([
            'code' => strtoupper($validated['newCurrencyCode']),
            'name' => trim($validated['newCurrencyName']),
            'symbol' => trim($validated['newCurrencySymbol']),
            'is_active' => true,
            'automatic_updates_enabled' => $validated['newCurrencyAutomatic'],
        ]);
        $this->baseCurrency = $currency->code;
        $this->reset(['newCurrencyCode', 'newCurrencyName', 'newCurrencySymbol', 'newCurrencyAutomatic']);
        $this->loadCurrentRate();
        session()->flash('message', "Moneda {$currency->code} agregada. Registra su tasa inicial.");
    }

    public function approveRequest(int $requestId, ExchangeRateService $service): void
    {
        $this->authorizeAdministration();
        $request = ExchangeRateChangeRequest::where('status', ExchangeRateChangeRequest::STATUS_PENDING)->findOrFail($requestId);
        $rate = $service->approveManualRate($request->base_currency, $request->quote_currency, $request->proposed_rate, $request->rate_date->toDateString(), auth()->user());
        $request->update([
            'status' => ExchangeRateChangeRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'exchange_rate_id' => $rate->id,
        ]);
        $this->loadCurrentRate();
        session()->flash('message', 'Solicitud aprobada y tasa global actualizada.');
    }

    public function rejectRequest(int $requestId): void
    {
        $this->authorizeAdministration();
        ExchangeRateChangeRequest::where('status', ExchangeRateChangeRequest::STATUS_PENDING)->findOrFail($requestId)->update([
            'status' => ExchangeRateChangeRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        session()->flash('message', 'Solicitud rechazada. La tasa no cambia.');
    }

    public function render(): View
    {
        return view('livewire.finanzas.exchange-rate-manager', [
            'rates' => ExchangeRate::query()
                ->with(['approver', 'staleApprover'])
                ->latest('rate_date')
                ->latest('id')
                ->limit(50)
                ->get(),
            'currencies' => Currency::query()->orderByRaw("code = 'NIO' desc")->orderBy('code')->get(),
            'changeRequests' => ExchangeRateChangeRequest::query()
                ->with(['requester', 'reviewer'])
                ->when(! $this->canAdminister(), fn ($query) => $query->where('requested_by', auth()->id()))
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function canAdminister(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    private function authorizeAdministration(): void
    {
        abort_unless($this->canAdminister(), 403);
    }

    private function loadCurrentRate(): void
    {
        $current = ExchangeRate::query()
            ->where('base_currency', strtoupper($this->baseCurrency))
            ->where('quote_currency', 'NIO')
            ->latest('rate_date')
            ->latest('id')
            ->first();
        $this->rate = $current?->rate ?? '';
        $this->rateDate = $current?->rate_date?->toDateString() ?? now()->toDateString();
    }
}
