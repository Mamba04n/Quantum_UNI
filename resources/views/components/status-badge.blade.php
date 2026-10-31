@props([
    'tone' => 'slate',
])

@php
    $classes = [
        'slate' => 'bg-slate-100 text-slate-700',
        'red' => 'bg-red-50 text-red-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'sky' => 'bg-sky-50 text-sky-700',
    ][$tone] ?? 'bg-slate-100 text-slate-700';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-md px-2 py-1 text-[10px] font-black uppercase tracking-wider '.$classes]) }}>
    {{ $slot }}
</span>
