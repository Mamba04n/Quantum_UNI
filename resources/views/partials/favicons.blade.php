@php
    $faviconPath = public_path('favicon.png');
    $faviconVersion = file_exists($faviconPath) ? filemtime($faviconPath) : time();
@endphp
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $faviconVersion }}">
<link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $faviconVersion }}">
<link rel="apple-touch-icon" href="{{ asset('favicon.png') }}?v={{ $faviconVersion }}">
