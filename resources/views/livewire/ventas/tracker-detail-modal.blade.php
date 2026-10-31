<div>
    @if($showModal && $selectedItem)
        @include('livewire.ventas.partials.tracker.detail-modal')
    @endif

    @if($showConfirmModal)
        @include('livewire.ventas.partials.tracker.confirm-modal')
    @endif
</div>
