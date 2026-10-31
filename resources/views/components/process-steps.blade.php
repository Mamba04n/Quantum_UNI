@props(['steps', 'current' => null, 'cancelled' => false, 'label' => 'Etapas del proceso', 'compact' => false, 'singleRow' => false, 'wrapLabels' => false])
<nav aria-label="{{ $label }}" class="min-w-0">
    <ol class="grid w-full items-stretch gap-y-1" style="grid-template-columns: {{ $singleRow ? 'repeat('.count($steps).', minmax(0, 1fr))' : 'repeat(auto-fit, minmax(min(100%, '.($compact ? '6.5rem' : '9rem').'), 1fr))' }};">
        @foreach($steps as $step)
            @php
                $active = !$cancelled && $current === $loop->iteration;
                $completed = !$cancelled && $current !== null && $current > $loop->iteration;
            @endphp
            <li @if($active) aria-current="step" @endif data-step-state="{{ $active ? 'current' : ($completed ? 'completed' : 'future') }}" class="relative flex min-w-0 items-center justify-center {{ $wrapLabels ? 'min-h-12 px-3 py-2 text-xs' : ($compact ? ($singleRow ? 'h-9 px-2 py-2 text-xs' : 'px-3 py-2 text-xs') : 'gap-2 px-5 py-2.5 text-sm') }} {{ $active ? 'font-semibold text-white' : ($completed ? 'font-medium text-emerald-800' : 'text-gray-500') }}">
                <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full {{ $active ? 'fill-slate-800 stroke-slate-800' : ($completed ? 'fill-emerald-50 stroke-emerald-200' : 'fill-gray-50 stroke-gray-200') }}" viewBox="0 0 200 44" preserveAspectRatio="none">
                    <polygon points="{{ $loop->first ? '1,1 185,1 199,22 185,43 1,43' : '1,1 185,1 199,22 185,43 1,43 15,22' }}" stroke-width="1" vector-effect="non-scaling-stroke" />
                </svg>
                <span class="relative flex min-w-0 items-center {{ $singleRow ? 'gap-1' : 'gap-2' }}">
                    @if($completed)
                        <svg class="{{ $singleRow ? 'h-3 w-3' : 'h-4 w-4' }} shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6" /></svg>
                    @elseif(!$compact)
                        <span class="font-semibold" aria-hidden="true">{{ $loop->iteration }}</span>
                    @endif
                    <span class="min-w-0 {{ $wrapLabels ? 'whitespace-normal leading-4' : ($singleRow ? 'truncate' : 'break-words') }} text-center" title="{{ $step }}">{{ $step }}</span>
                </span>
                @if($active)<span class="sr-only">Etapa actual</span>@endif
                @if($completed)<span class="sr-only">Etapa completada</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
