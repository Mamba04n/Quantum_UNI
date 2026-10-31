<?php

namespace App\Livewire\Ventas;

use App\Livewire\Ventas\Concerns\OrderTracker\ManagesTrackerModals;
use Livewire\Attributes\On;
use Livewire\Component;

class TrackerDetailModal extends Component
{
    use ManagesTrackerModals;

    #[On('open-tracker-details')]
    public function showDetails(int $id, string $type, ?int $orderStatusItemId = null): void
    {
        $this->openDetails($id, $type, $orderStatusItemId);
    }

    public function render()
    {
        return view('livewire.ventas.tracker-detail-modal');
    }
}
