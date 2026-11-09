@php
    $owner = $p->responsible?->name ?? 'Sin responsable';
    $ownerInitials = mb_strtoupper(mb_substr(trim($owner), 0, 2, 'UTF-8'), 'UTF-8');
    $canMove = auth()->user()->hasPermissionTo('prospects.move') && !$p->discarded_at && !$p->opportunity;
    $late = $p->next_contact_at && $p->next_contact_at->lt(today()) && !$p->discarded_at && !$p->opportunity;
@endphp

<article
    wire:key="prospect-{{ $p->id }}-{{ $p->version }}"
    class="group overflow-hidden rounded-xl border border-gray-200/90 bg-white shadow-2xs transition-all hover:border-[#b50e0e]/50 hover:shadow-sm"
    @if($canMove)
        draggable="true"
        x-on:dragstart="drag = { id: {{ $p->id }}, version: {{ $p->version }} }; $event.dataTransfer.setData('text/plain', '{{ $p->id }}'); $event.dataTransfer.effectAllowed = 'move'"
        x-on:dragend="drag = null"
    @endif
>
    <button
        type="button"
        data-prospect-open="{{ $p->id }}"
        wire:click="open({{ $p->id }})"
        class="block w-full p-3.5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#b50e0e]"
        aria-label="Abrir prospecto {{ $p->name }}"
    >
        {{-- Título y Clasificación --}}
        <div class="flex items-start justify-between gap-2">
            <span class="min-w-0 font-bold text-sm text-gray-900 group-hover:text-[#b50e0e] transition-colors leading-snug break-words">
                {{ $p->name }}
            </span>

            @if($p->classification)
                <span
                    class="shrink-0 rounded-md bg-rose-50 border border-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-[#b50e0e]"
                    title="{{ config('acquisition.classes')[$p->classification] ?? '' }}"
                >
                    {{ $p->classification }}
                </span>
            @endif
        </div>

        {{-- Fuente y Ubicación --}}
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-400">
            <span>{{ $p->source }}</span>
            @if($p->city)
                <span>&bull;</span>
                <span class="flex items-center gap-1 text-gray-600">
                    <x-workspace-icon name="location" class="h-3 w-3 text-gray-400" />
                    <span>{{ $p->city }}</span>
                </span>
            @endif
            @if($p->sector)
                <span>&bull;</span>
                <span class="text-gray-500 truncate max-w-28">{{ $p->sector }}</span>
            @endif
        </div>

        {{-- Necesidad o Maquinaria --}}
        @if($p->need || $p->products || $p->services)
            <div class="mt-2.5 rounded-lg bg-gray-50/80 border border-gray-100 p-2 text-xs text-gray-600 leading-relaxed line-clamp-2">
                <span class="font-medium text-gray-700">Necesidad:</span> {{ \Illuminate\Support\Str::limit($p->need ?: ($p->products ?: $p->services), 90) }}
            </div>
        @endif

        {{-- Responsable y Estado de Seguimiento --}}
        <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-2.5">
            {{-- Responsable --}}
            <div class="flex items-center gap-1.5 min-w-0" title="Responsable: {{ $owner }}">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-700">
                    {{ $ownerInitials }}
                </span>
                <span class="max-w-24 truncate text-xs text-gray-600 font-medium">{{ $owner }}</span>
            </div>

            {{-- Fecha de contacto / Alerta --}}
            <div class="shrink-0 text-right text-xs">
                @if($p->discarded_at)
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600">Descartado</span>
                @elseif($p->opportunity)
                    <span class="rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">
                        Oportunidad #{{ $p->opportunity->id }}
                    </span>
                @elseif($p->next_contact_at)
                    @if($late)
                        <span class="rounded bg-rose-50 border border-rose-200 px-1.5 py-0.5 text-[10px] font-bold text-rose-700" title="Seguimiento vencido">
                            Vencido {{ $p->next_contact_at->format('d/m') }}
                        </span>
                    @elseif($p->next_contact_at->isToday())
                        <span class="rounded bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">
                            Hoy {{ $p->next_contact_at->format('H:i') !== '00:00' ? $p->next_contact_at->format('H:i') : '' }}
                        </span>
                    @else
                        <span class="text-[11px] text-gray-500">
                            {{ $p->next_contact_at->format('d/m/Y') }}
                        </span>
                    @endif
                @else
                    <span class="text-[11px] text-gray-400">
                        {{ ($p->last_contact_at ?? $p->created_at)->format('d/m/Y') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Enlace de Acción Rápida --}}
        <div class="mt-2 flex items-center justify-between text-xs font-semibold text-gray-600 group-hover:text-[#b50e0e] transition-colors">
            <span>
                {{ $p->discarded_at || $p->opportunity ? 'Ver historial' : ($key === 'calificados' ? 'Revisar oportunidad' : 'Ver prospecto y seguimiento') }}
            </span>
            <x-workspace-icon name="arrow" class="h-3 w-3" />
        </div>
    </button>

    {{-- Desplegable para cambio de etapa rápido --}}
    @if($canMove)
        <details class="border-t border-gray-100 bg-gray-50/50 px-3 py-1.5 text-xs text-gray-500">
            <summary class="cursor-pointer font-medium hover:text-gray-900 transition-colors">
                Mover a otra etapa
            </summary>
            <div class="mt-2 pb-1">
                <select
                    aria-label="Mover {{ $p->name }} a otra etapa"
                    wire:loading.attr="disabled"
                    x-on:change="$wire.move({{ $p->id }}, {{ $p->version }}, $event.target.value); $event.target.value = @js($key)"
                    class="w-full rounded-lg border-gray-200 bg-white py-1.5 px-2 text-xs font-semibold text-gray-700 focus:border-[#b50e0e] focus:ring-[#b50e0e]/20"
                >
                    @foreach(config('acquisition.stages') as $stageKey => $stageLabel)
                        <option value="{{ $stageKey }}" @selected($stageKey === $key)>{{ $stageLabel }}</option>
                    @endforeach
                </select>
            </div>
        </details>
    @endif
</article>
