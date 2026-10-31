@props(['current' => 1, 'cancelled' => false, 'compact' => false])
@php($steps = ['Solicitud', 'Licitación', 'Oferta cliente', 'Aprobación', 'Orden de compra'])
@if($compact)
    <details class="sm:hidden">
        <summary class="cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-slate-800">{{ $cancelled ? 'Cancelada' : ($steps[$current - 1] ?? 'Proceso comercial') }} <span class="font-normal text-gray-500">· Paso {{ $current }} de {{ count($steps) }}</span></summary>
        <div class="mt-2"><x-process-steps :steps="$steps" :current="$current" :cancelled="$cancelled" :compact="true" label="Etapas del proceso comercial" /></div>
    </details>
@endif
<div @class(['hidden sm:block' => $compact])>
    <x-process-steps :steps="$steps" :current="$current" :cancelled="$cancelled" :compact="$compact" label="Progreso del proceso comercial" />
</div>
