<section class="erp-section">
    <div class="erp-section-header">
        <div>
            <h4 class="text-sm font-black text-slate-900">Comentarios del proceso</h4>
            <p class="mt-1 text-[10px] text-slate-500">Historial de observaciones y acuerdos por fase.</p>
        </div>
    </div>

    <div class="mb-5 flex flex-col gap-3 md:flex-row">
        <textarea wire:model="newComment" class="flex-1 rounded-lg border-slate-200 bg-white/70 text-xs focus:border-slate-800 focus:ring-slate-500" placeholder="Escribe un comentario..." rows="2"></textarea>
        <div class="flex gap-2 md:flex-col">
            <select wire:model="commentVisibility" class="rounded-lg border-slate-200 bg-white/70 text-[10px] font-bold">
                <option value="internal">Interno</option>
                <option value="customer">Visible al cliente</option>
            </select>
            <button type="button" wire:click="addComment" class="erp-button-primary py-2 text-[11px]">Comentar</button>
        </div>
    </div>

    @if($tracker && $tracker->comments->isNotEmpty())
        <div class="divide-y divide-slate-100 border-y border-slate-200/70">
            @foreach($tracker->comments->sortByDesc('created_at') as $comment)
                <div class="py-3">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-black text-slate-900">{{ $comment->user->name ?? 'Sistema' }}</span>
                        <span class="text-[9px] font-bold text-slate-400">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                        <span class="rounded-md px-2 py-0.5 text-[9px] font-black uppercase tracking-widest {{ $comment->visibility === 'internal' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $comment->visibility }}</span>
                    </div>
                    <p class="whitespace-pre-line text-xs text-slate-700">{{ $comment->comment }}</p>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-inline message="No hay comentarios en esta solicitud." />
    @endif
</section>
