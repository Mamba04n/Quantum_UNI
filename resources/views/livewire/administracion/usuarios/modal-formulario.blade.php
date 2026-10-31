@if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
        <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-5xl border border-gray-100 flex flex-col max-h-[90vh]">
            @include('livewire.administracion.usuarios.formulario.encabezado')

            <div class="p-6 space-y-6 overflow-y-auto">
                @include('livewire.administracion.usuarios.formulario.datos-personales')
                @include('livewire.administracion.usuarios.formulario.datos-laborales')
                @include('livewire.administracion.usuarios.formulario.cuenta-acceso')
                @include('livewire.administracion.usuarios.formulario.roles')
                @include('livewire.administracion.usuarios.formulario.estado-notas')
            </div>

            @include('livewire.administracion.usuarios.formulario.acciones')
        </div>
    </div>
@endif
