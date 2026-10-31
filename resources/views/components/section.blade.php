@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'erp-section']) }}>
    @if($title || $description || isset($actions))
        <div class="erp-section-header">
            <div>
                @if($title)
                    <h2 class="erp-section-title">{{ $title }}</h2>
                @endif
                @if($description)
                    <p class="erp-section-help">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
