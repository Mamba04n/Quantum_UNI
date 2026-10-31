<div class="p-6 sm:p-8 space-y-6 font-sans bg-slate-50">
    @include('livewire.administracion.indicadores.panel-kpis.encabezado')
    @include('livewire.administracion.indicadores.panel-kpis.filtros')
    @include('livewire.administracion.indicadores.panel-kpis.tarjetas')

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        @include('livewire.administracion.indicadores.panel-kpis.graficas.ventas-mes')
        @include('livewire.administracion.indicadores.panel-kpis.graficas.inventario-marca')
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        @include('livewire.administracion.indicadores.panel-kpis.resumenes.top-clientes')
        @include('livewire.administracion.indicadores.panel-kpis.resumenes.cierre-vendedor')
        @include('livewire.administracion.indicadores.panel-kpis.resumenes.alertas')
    </section>
</div>
