<div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <x-order-context :purchase-order="$document->purchaseOrder" />
        @include('livewire.administracion.compras.documentos.orden-compra.encabezado')
        @include('livewire.administracion.compras.documentos.orden-compra.alertas')

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <form wire:submit.prevent="saveDocumentData" class="space-y-6">
                @include('livewire.administracion.compras.documentos.orden-compra.formulario.datos-documento')
                @include('livewire.administracion.compras.documentos.orden-compra.formulario.consignatario')
                @include('livewire.administracion.compras.documentos.orden-compra.formulario.envio')
                @include('livewire.administracion.compras.documentos.orden-compra.formulario.comentarios')
                @include('livewire.administracion.compras.documentos.orden-compra.formulario.acciones')
            </form>

            @include('livewire.administracion.compras.documentos.orden-compra.aside.productos-aprobados')
        </div>
    </div>
</div>
