@props([
    'documents' => collect(),
])

<div {{ $attributes->merge(['class' => 'divide-y divide-slate-100 border-y border-slate-200/70']) }}>
    @forelse($documents as $document)
        <div class="flex items-center justify-between gap-3 py-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-800">{{ $document->file_name ?? $document['name'] ?? 'Documento' }}</p>
                <p class="text-xs font-medium text-slate-400">{{ $document->phase ?? $document['phase'] ?? 'general' }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                {{ $actions ?? '' }}
            </div>
        </div>
    @empty
        <x-empty-inline message="No hay documentos generados todavía." />
    @endforelse
</div>
