<section aria-label="Comentarios de la fase">
    @unless($hideHeading ?? false)
    <div class="mb-3">
        <div>
            <h4 class="text-sm font-semibold text-slate-900">Comentarios de la fase</h4>
            
        </div>
    </div>
    @endunless

    <div class="mb-5 flex flex-col gap-3 md:flex-row">
        <textarea wire:model="newComment" aria-label="Comentario de la fase" maxlength="2000" class="flex-1 rounded-lg border-slate-200 bg-white/70 text-sm focus:border-slate-800 focus:ring-slate-500" placeholder="Escribe un comentario..." rows="2"></textarea>
        <div class="flex gap-2 md:flex-col">
            <select wire:model="commentVisibility" aria-label="Visibilidad del comentario" class="rounded-lg border-slate-200 bg-white/70 text-sm font-bold">
                <option value="internal">Interno</option>
                <option value="customer">Visible al cliente</option>
            </select>
            <button type="button" wire:click="addComment" wire:loading.attr="disabled" wire:target="addComment,commentFiles" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50">Comentar</button>
        </div>
    </div>

    <div class="mb-3 space-y-2 text-sm">
        <label class="block font-medium text-slate-700" for="phase-comment-files">Adjuntar archivos</label>
        <input id="phase-comment-files" type="file" multiple wire:model="commentFiles" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt" class="block w-full text-sm text-slate-600" />
        <p class="text-gray-500">Hasta 5 archivos de 10 MB. Imágenes, PDF y documentos.</p>
        <p wire:loading wire:target="commentFiles" role="status" class="text-slate-600">Subiendo archivos…</p>
        @foreach($commentFiles as $index => $file)
            <div class="flex min-w-0 items-center gap-2" wire:key="comment-file-{{ $index }}">
                <span class="min-w-0 break-all">{{ $file->getClientOriginalName() }}</span>
                <button type="button" wire:click="removeCommentFile({{ $index }})" class="shrink-0 text-red-700">Quitar</button>
            </div>
        @endforeach
        @error('commentFiles')<p class="text-red-700">{{ $message }}</p>@enderror
        @error('commentFiles.*')<p class="text-red-700">{{ $message }}</p>@enderror
    </div>
    @error('newComment')<p class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
    @if($tracker && $tracker->comments->isNotEmpty())
        <div class="divide-y divide-slate-100 border-y border-slate-200/70">
            @foreach($tracker->comments->sortByDesc('created_at') as $comment)
                <div class="py-3">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-slate-900">{{ $comment->user->name ?? 'Sistema' }}</span>
                        <span class="text-xs font-bold text-slate-400">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold  {{ $comment->visibility === 'internal' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $comment->visibility === 'internal' ? 'Interno' : 'Visible al cliente' }}</span>
                    </div>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $comment->comment }}</p>
                    @foreach($comment->activity?->attachments ?? [] as $attachment)
                        <button type="button" wire:click="downloadCommentFile({{ $attachment->id }})" wire:loading.attr="disabled" class="mt-2 block max-w-full break-all text-left text-sm font-medium text-slate-700 underline hover:text-slate-900">{{ $attachment->file_name }}</button>
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        <x-empty-inline message="No hay comentarios en esta solicitud." />
    @endif
</section>
