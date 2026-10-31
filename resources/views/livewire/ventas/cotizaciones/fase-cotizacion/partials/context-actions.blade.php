<x-context-action-bar>
    <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('workspace') }}" class="font-medium text-slate-600 hover:text-slate-900 transition-colors">Inicio</a>
        <span aria-hidden="true" class="text-slate-300">/</span>
        <a href="{{ route('sales.quotations.phase') }}" class="font-medium text-slate-600 hover:text-slate-900 transition-colors">Cotizaciones</a>
        <span aria-hidden="true" class="text-slate-300">/</span>
        <span aria-current="page" class="font-semibold text-slate-900">{{ $selectedQuote->reference }}</span>
    </nav>

    <x-slot:workflow>
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.status-pipeline')
    </x-slot:workflow>
</x-context-action-bar>
