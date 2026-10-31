<?php

namespace App\Livewire\Ventas;

use App\Livewire\Ventas\Concerns\OrderTracker\ManagesTrackerKanban;
use App\Livewire\Ventas\Concerns\OrderTracker\ManagesTrackerList;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UnifiedOrderTracker extends Component
{
    use ManagesTrackerKanban;
    use ManagesTrackerList;

    public string $viewMode = 'kanban';

    public string $search = '';

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['kanban', 'list'], true) ? $mode : 'kanban';
    }

    public function render()
    {
        if ($this->viewMode === 'kanban') {
            $quotes = QuotationRequest::query()->with('items')->latest('updated_at')->get();
            $orders = SaleOrder::query()->with([
                'items',
                'orderStatus.items.product',
                'orderStatus.items.supplier',
                'orderStatus.items.saleOrderItem',
            ])->latest('updated_at')->get();
            $board = $this->getKanbanBoard($quotes, $orders);
            $listItems = [];
        } else {
            $board = [];
            $listItems = $this->getListItems($this->search);
        }

        return view('livewire.ventas.unified-order-tracker', [
            'board' => $board,
            'listItems' => $listItems,
        ]);
    }
}
