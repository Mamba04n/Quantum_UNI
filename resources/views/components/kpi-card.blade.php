@props(['title', 'value', 'detail', 'accent' => 'slate'])

@php
    $bgStyles = [
        'emerald' => 'bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-emerald-500/30',
        'amber' => 'bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-amber-500/30',
        'indigo' => 'bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-indigo-500/30',
        'sky' => 'bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-sky-500/30',
        'slate' => 'bg-gradient-to-br from-slate-800 to-slate-950 text-white shadow-slate-900/30',
    ];

    $textStyles = [
        'emerald' => 'text-emerald-100',
        'amber' => 'text-amber-100',
        'indigo' => 'text-indigo-100',
        'sky' => 'text-sky-100',
        'slate' => 'text-slate-300',
    ];
@endphp

<div class="relative overflow-hidden rounded-[2rem] p-7 shadow-lg {{ $bgStyles[$accent] ?? $bgStyles['slate'] }} transition-transform duration-300 hover:-translate-y-1 hover:shadow-xl group">
    <!-- Efectos decorativos de fondo -->
    <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10 blur-2xl transition-transform duration-500 group-hover:scale-150"></div>
    <div class="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-black/10 blur-2xl"></div>

    <div class="relative z-10 flex flex-col h-full justify-between gap-4">
        <p class="text-[11px] font-black uppercase tracking-widest text-white/80">{{ $title }}</p>
        <div>
            <span class="text-4xl lg:text-5xl font-black tracking-tight drop-shadow-sm">{{ $value }}</span>
        </div>
        <div class="mt-2 flex items-center gap-2">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-white/50"></span>
            <p class="text-xs font-semibold {{ $textStyles[$accent] ?? $textStyles['slate'] }}">{{ $detail }}</p>
        </div>
    </div>
</div>
