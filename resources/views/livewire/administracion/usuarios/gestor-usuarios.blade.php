<div class="p-6 sm:p-8 font-sans">
    @include('livewire.administracion.usuarios.encabezado')
    @include('livewire.administracion.usuarios.alertas')
    @include('livewire.administracion.usuarios.aviso')
    @include('livewire.administracion.usuarios.buscador')
    @include('livewire.administracion.usuarios.tabla')
    
    <div class="mt-4">
        {{ $users->links() }}
    </div>

    @include('livewire.administracion.usuarios.modal-formulario')
    @include('livewire.administracion.usuarios.modal-eliminar')
</div>
