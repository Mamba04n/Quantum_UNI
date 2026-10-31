<?php

namespace App\Livewire\Ventas\Cotizaciones\LicitacionProveedor;

use App\Livewire\Ventas\Cotizaciones\Shared\BaseGestorCotizacion;
use App\Services\SalesWorkflowService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class GestorLicitacionProveedor extends BaseGestorCotizacion
{
    public function mount($quoteId = null)
    {
        if ($quoteId) {
            $this->selectQuote((int)$quoteId);
            return;
        }

        $this->activeTab = 'bid';
        session(['sales_quotation_active_tab' => $this->activeTab]);
    }

    public function render(SalesWorkflowService $workflow)
    {
        return parent::render($workflow);
    }
}
