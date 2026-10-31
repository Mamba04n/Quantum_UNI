<x-layouts.storefront>
    <x-slot:title>{{ $post->titulo }} | Quantum Soluciones</x-slot:title>
    <article class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
        <p class="font-black uppercase tracking-wider text-red-700">{{ $post->categoria ?: 'Blog técnico' }}</p>
        <h1 class="mt-3 text-4xl font-black sm:text-5xl">{{ $post->titulo }}</h1>
        @if($post->resumen)<p class="mt-6 text-xl leading-8 text-slate-600">{{ $post->resumen }}</p>@endif
        @if($post->imagen)<img src="{{ \App\Support\StorefrontUrl::blogImage($post->imagen) }}" alt="{{ $post->titulo }}" class="mt-10 max-h-[32rem] w-full rounded-3xl object-cover">@endif
        <div class="mt-10 whitespace-pre-line text-lg leading-8 text-slate-700">{{ $post->contenido }}</div>
    </article>
</x-layouts.storefront>
