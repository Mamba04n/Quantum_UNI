<section class="mx-auto max-w-7xl">
    @include('livewire.administracion.proveedores.detalle.acciones')

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @include('livewire.administracion.proveedores.detalle.encabezado')
        @include('livewire.administracion.proveedores.detalle.pestanas')

        <div class="p-5 sm:p-6">
            @if($activeTab === 'general')
                @include('livewire.administracion.proveedores.detalle.informacion-general')
            @elseif($activeTab === 'brands')
                @include('livewire.administracion.proveedores.detalle.lista-simple', ['items' => $selectedSupplier->brands, 'field' => 'name', 'model' => \App\Models\Brand::class, 'input' => 'brandInput', 'action' => 'addBrand', 'placeholder' => 'Ej: Bosch, Volvo, Doosan'])
            @elseif($activeTab === 'products')
                @include('livewire.administracion.proveedores.detalle.lista-simple', ['items' => $selectedSupplier->productTypes, 'field' => 'name', 'model' => \App\Models\ProductType::class, 'input' => 'productTypeInput', 'action' => 'addProductType', 'placeholder' => 'Tipo de producto'])
                @include('livewire.administracion.proveedores.detalle.opciones-rapidas', ['options' => $productTypeOptions, 'property' => 'productTypeInput'])
            @elseif($activeTab === 'machines')
                @include('livewire.administracion.proveedores.detalle.lista-simple', ['items' => $selectedSupplier->machineTypes, 'field' => 'name', 'model' => \App\Models\MachineType::class, 'input' => 'machineTypeInput', 'action' => 'addMachineType', 'placeholder' => 'Tipo de maquinaria'])
                @include('livewire.administracion.proveedores.detalle.opciones-rapidas', ['options' => $machineTypeOptions, 'property' => 'machineTypeInput'])
            @elseif($activeTab === 'payments')
                @include('livewire.administracion.proveedores.detalle.lista-simple', ['items' => $selectedSupplier->paymentMethods, 'field' => 'name', 'model' => \App\Models\PaymentMethod::class, 'input' => 'paymentMethodInput', 'action' => 'addPaymentMethod', 'placeholder' => 'Metodo de pago'])
                @include('livewire.administracion.proveedores.detalle.opciones-rapidas', ['options' => $paymentMethodOptions, 'property' => 'paymentMethodInput'])
            @elseif($activeTab === 'banks')
                @include('livewire.administracion.proveedores.detalle.datos-bancarios')
            @elseif($activeTab === 'history')
                @include('livewire.administracion.proveedores.detalle.historial')
            @endif
        </div>
    </div>
</section>
