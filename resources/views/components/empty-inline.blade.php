@props([
    'message' => 'No hay registros disponibles.',
])

<p {{ $attributes->merge(['class' => 'py-6 text-sm font-semibold text-slate-400']) }}>
    {{ $message }}
</p>
