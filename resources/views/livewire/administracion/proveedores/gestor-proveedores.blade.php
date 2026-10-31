<div class="p-6 font-sans sm:p-8">
    @include('livewire.administracion.proveedores.mensaje')

    @if($showForm)
        @include('livewire.administracion.proveedores.formulario')
    @elseif($selectedSupplier)
        @include('livewire.administracion.proveedores.detalle')
    @else
        @include('livewire.administracion.proveedores.listado')
    @endif
</div>
