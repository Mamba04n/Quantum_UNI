@props(['name' => 'box'])
@php
    $paths = [
        'home' => 'm3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',
        'quote' => 'M8 3h8l4 4v14H4V3h4Zm7 0v5h5M8 12h8m-8 4h5',
        'box' => 'm3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 5 9-5m-9 5v9M7 5l10 5',
        'truck' => 'M3 6h11v11H3V6Zm11 4h4l3 4v3h-7M8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm12 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
        'pin' => 'M19 10c0 6-7 11-7 11S5 16 5 10a7 7 0 1 1 14 0Zm-4 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'user' => 'M20 21a8 8 0 0 0-16 0m12-14a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        'support' => 'M4 14v-2a8 8 0 0 1 16 0v2M4 12H2v7h4v-7Zm16 0h2v7h-4v-7Zm0 7v2h-6',
        'search' => 'm21 21-5-5m2-6a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
        'arrow' => 'M4 12h16m-6-6 6 6-6 6',
        'back' => 'M20 12H4m6-6-6 6 6 6',
        'plus' => 'M12 5v14M5 12h14',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'close' => 'm6 6 12 12M6 18 18 6',
        'logout' => 'M9 4H4v16h5m6-13 5 5-5 5m-7-5h12',
        'check' => 'm8 12 3 3 5-6m5 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'book' => 'M12 5C8 2 4 3 2 4v15c4-2 7-1 10 1m0-15c4-3 8-2 10-1v15c-4-2-7-1-10 1V5Z',
        'mail' => 'M3 5h18v14H3V5Zm0 1 9 7 9-7',
        'phone' => 'm5 3 4 1 1 5-3 2a15 15 0 0 0 6 6l2-3 5 1 1 4c-1 5-8 2-12-2S1 4 5 3Z',
        'machine' => 'M3 15h13l3 4H3v-4Zm3-3V6h7v6H6Zm7-6 5-3 3 8-3 2m0-10v8M6 19v2m10-2v2',
        'download' => 'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
        'clock' => 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'chat' => 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8ZM8 9h8m-8 4h5',
    ];
@endphp
<svg {{ $attributes->class(['cp-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['box'] }}" /></svg>
