<div class="overflow-x-auto pb-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-400" tabindex="0" role="region" aria-label="Tablero de cotizaciones por etapa" data-testid="commercial-kanban-board">
    <div class="flex min-w-max items-start gap-3">
        @foreach($flowStages as $key => $stage)
            @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.kanban-column', [
                'key' => $key,
                'stage' => $stage,
                'quotes' => $kanbanColumns[$key],
                'count' => $stageCounts[$key] ?? 0,
            ])
        @endforeach
    </div>
</div>
