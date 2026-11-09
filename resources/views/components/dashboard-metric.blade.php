@props(['label', 'value', 'icon' => 'chart', 'tone' => 'blue', 'context' => null, 'compact' => false])
@php
    $iconMap = [
        'chart' => 'chart',
        'wallet' => 'wallet',
        'box' => 'box',
        'cart' => 'cart',
        'users' => 'user',
        'document' => 'document',
        'check' => 'check',
    ];
    $iconName = $iconMap[$icon] ?? 'chart';
@endphp

<article class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 sm:p-5 shadow-xs transition-all hover:border-gray-200 hover:shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 truncate mb-1">{{ $label }}</p>
            <p class="break-words text-2xl sm:text-3xl font-extrabold tabular-nums tracking-tight text-gray-900 leading-tight">{{ $value }}</p>
            @if($slot->isNotEmpty() || $context)
                <div class="text-xs text-gray-500 font-medium leading-relaxed truncate mt-1.5 flex items-center gap-1.5 flex-wrap">
                    {{ $slot }}{{ $context }}
                </div>
            @endif
        </div>
        <span class="inline-flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-[#b50e0e] shadow-2xs">
            <x-portal.icon :name="$iconName" class="w-5 h-5 sm:w-5.5 sm:h-5.5" />
        </span>
    </div>
</article>
