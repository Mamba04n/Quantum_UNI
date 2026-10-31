@php
    $owner = $p->responsible?->name ?? 'Sin responsable';
    $canMove = auth()->user()->hasPermissionTo('prospects.move') && !$p->discarded_at && !$p->opportunity;
    $late = $p->next_contact_at && $p->next_contact_at->lt(today()) && !$p->discarded_at && !$p->opportunity;
@endphp
<article wire:key="prospect-{{ $p->id }}-{{ $p->version }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-colors hover:border-slate-400"
    @if($canMove) draggable="true" x-on:dragstart="drag = { id: {{ $p->id }}, version: {{ $p->version }} }; $event.dataTransfer.setData('text/plain', '{{ $p->id }}'); $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="drag = null" @endif>
    <button type="button" data-prospect-open="{{ $p->id }}" wire:click="open({{ $p->id }})" class="block w-full p-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-slate-700" aria-label="Abrir prospecto {{ $p->name }}">
        <span class="flex items-start justify-between gap-2">
            <span class="min-w-0 break-words text-sm font-semibold leading-5 text-slate-900">{{ $p->name }}</span>
            @if($p->classification)<span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700" title="{{ config('acquisition.classes')[$p->classification] ?? '' }}">{{ $p->classification }}<span class="sr-only"> · {{ config('acquisition.classes')[$p->classification] ?? '' }}</span></span>@endif
        </span>
        <span class="mt-1.5 block text-xs text-slate-500">{{ $p->source }}</span>
        @if($p->city)<span class="mt-2 flex items-center gap-2 text-xs text-slate-600"><x-workspace-icon name="location" class="h-3.5 w-3.5"/>{{ $p->city }}</span>@endif
        @if($p->need || $p->products || $p->services)
            <span class="mt-2 flex items-start gap-2 text-sm text-slate-600"><x-workspace-icon name="target" class="mt-0.5 h-3.5 w-3.5"/><span class="line-clamp-2 break-words">{{ \Illuminate\Support\Str::limit($p->need ?: ($p->products ?: $p->services), 100) }}</span></span>
        @elseif($p->sector)
            <span class="mt-2 flex items-start gap-2 text-xs text-slate-600"><x-workspace-icon name="building" class="h-3.5 w-3.5"/>{{ $p->sector }}</span>
        @endif
        <span class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
            <span class="flex min-w-0 items-center gap-2" title="Responsable: {{ $owner }}">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600" aria-hidden="true">{{ mb_strtoupper(mb_substr($owner, 0, 2)) }}</span>
                <span class="max-w-28 truncate text-xs text-slate-600">{{ $owner }}</span>
            </span>
            <span class="flex items-center gap-1.5 text-xs {{ $late ? 'text-amber-700' : 'text-slate-500' }}">
                <x-workspace-icon :name="$p->next_contact_at && !$p->opportunity && !$p->discarded_at ? 'clock' : 'calendar'" class="h-3.5 w-3.5"/>
                <span class="text-right">
                    @if($p->discarded_at)<span class="block">Descartado</span><time datetime="{{ $p->discarded_at->toDateString() }}">{{ $p->discarded_at->format('d/m/Y') }}</time>
                    @elseif($p->opportunity)<span class="text-emerald-700">Oportunidad #{{ $p->opportunity->id }}</span>
                    @elseif($p->next_contact_at)<span class="block">{{ $p->next_contact_at->isToday() ? 'Seguimiento HOY' : ($late ? 'Seguimiento vencido' : 'Próximo contacto') }}</span><time datetime="{{ $p->next_contact_at->toDateString() }}">{{ $p->next_contact_at->format('d/m/Y') }}</time>
                    @else<span class="block">{{ $p->last_contact_at ? 'Último contacto' : 'Creado' }}</span><time datetime="{{ ($p->last_contact_at ?? $p->created_at)->toDateString() }}">{{ ($p->last_contact_at ?? $p->created_at)->format('d/m/Y') }}</time>@endif
                </span>
            </span>
        </span>
        <span class="mt-3 flex items-center justify-between gap-2 text-xs font-semibold {{ $key === 'calificados' && !$p->opportunity && !$p->discarded_at ? 'text-[#9A0101]' : 'text-slate-700' }}"><span>{{ $p->discarded_at || $p->opportunity ? 'Ver historial' : ($key === 'calificados' ? 'Revisar oportunidad comercial' : 'Ver prospecto y seguimiento') }}</span><x-workspace-icon name="arrow" class="h-3.5 w-3.5"/></span>
    </button>
    @if($canMove)
        <details class="border-t border-slate-100 px-3 py-2 text-xs text-slate-500">
            <summary class="cursor-pointer py-1 font-medium">Cambiar etapa</summary>
            <label class="mt-2 block">Mover a<select aria-label="Mover {{ $p->name }} a otra etapa" wire:loading.attr="disabled" x-on:change="$wire.move({{ $p->id }}, {{ $p->version }}, $event.target.value); $event.target.value = @js($key)" class="mt-1 w-full rounded-lg border-gray-200 py-2 text-sm">@foreach(config('acquisition.stages') as $stageKey => $stageLabel)<option value="{{ $stageKey }}" @selected($stageKey === $key)>{{ $stageLabel }}</option>@endforeach</select></label>
        </details>
    @endif
</article>
