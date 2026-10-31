<?php

namespace App\Livewire\Ventas\Cotizaciones\Kanban;

use App\Livewire\Ventas\Cotizaciones\Shared\BaseGestorCotizacion;
use App\Services\SalesWorkflowService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class GestorKanban extends BaseGestorCotizacion
{
    public function mount()
    {
        $this->selectedQuoteId = null;
        $this->showDetail = false;
        $this->activeTab = 'kanban';
        session(['sales_quotation_active_tab' => $this->activeTab]);
    }

    public function render(SalesWorkflowService $workflow)
    {
        return parent::render($workflow);
    }
}
