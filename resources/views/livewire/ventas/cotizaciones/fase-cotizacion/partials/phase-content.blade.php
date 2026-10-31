@if($activeTab === 'bid' && in_array($currentStage, ['supplier_bid', 'supplier_response', 'supplier_selection']))
    @include('livewire.ventas.cotizaciones.licitacion-proveedor.panel-licitacion-proveedor')
@endif

@if($activeTab === 'offer' && in_array($currentStage, ['client_offer', 'order_changes']))
    @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.client-offer-panel')
@endif

@if($activeTab === 'approval' && $currentStage === 'customer_approval')
    @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.customer-approval-panel')
@endif

@if(in_array($currentStage, ['purchase_preparation', 'purchase_order_generated']))
    @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.purchase-preparation-panel')
@endif
