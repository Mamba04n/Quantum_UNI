@props(['label', 'value', 'icon' => 'chart', 'tone' => 'blue', 'context' => null, 'compact' => false])
@php
    $colors = match ($tone) {
        'teal' => ['border-t-teal-500', 'bg-teal-50 text-teal-700'],
        'amber' => ['border-t-amber-500', 'bg-amber-50 text-amber-700'],
        'violet' => ['border-t-violet-500', 'bg-violet-50 text-violet-700'],
        default => ['border-t-blue-500', 'bg-blue-50 text-blue-700'],
    };
@endphp
<article class="min-w-0 rounded-2xl border border-gray-100 border-t-2 {{ $colors[0] }} bg-white p-4 shadow-sm">
    <div class="{{ $compact ? 'mb-1' : 'mb-3' }} flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
        @unless($compact)<span class="inline-flex rounded-xl p-2 {{ $colors[1] }}"><x-workspace-icon :name="$icon" class="h-5 w-5" /></span>@endunless
    </div>
    <p class="break-words text-2xl font-extrabold tabular-nums tracking-tight text-slate-900">{{ $value }}</p>
    <div class="mt-2 text-xs text-slate-500">{{ $slot }}{{ $context }}</div>
</article>
