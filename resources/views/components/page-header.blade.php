@props([
    'kicker' => null,
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'erp-page-header flex flex-col gap-4 md:flex-row md:items-end md:justify-between']) }}>
    <div>
        @if($kicker)
            <p class="erp-kicker">{{ $kicker }}</p>
        @endif
        <h1 class="erp-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="erp-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
