<form wire:submit="addInternalNote" class="rounded-xl border border-amber-100 bg-amber-50/60 p-4 space-y-3">
    <h4 class="text-sm font-black text-amber-900">Notas internas</h4>
    <textarea wire:model="internalNote" rows="3" class="w-full rounded-xl border-amber-100 text-sm" placeholder="Nota no visible para el cliente..."></textarea>
    @error('internalNote') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 text-white text-sm font-bold">Guardar nota</button>

    <div class="space-y-2">
        @if($selectedTicket->quotation_request_id)
            @forelse($trackerComments as $comment)
                <div class="bg-white rounded-lg p-3 border border-amber-100">
                    <p class="text-xs text-gray-400 font-bold">{{ $comment->user?->name ?? 'Sistema' }} &middot; {{ $comment->created_at->format('d/m/Y H:i') }}</p>
                    <p class="text-sm text-gray-800 whitespace-pre-line">{{ $comment->comment }}</p>
                </div>
            @empty
                <p class="text-xs text-amber-700">Sin comentarios internos en el tracker.</p>
            @endforelse
        @else
            @foreach($selectedTicket->notes as $note)
                <div class="bg-white rounded-lg p-3 border border-amber-100">
                    <p class="text-xs text-gray-400 font-bold">{{ $note->admin?->name }} &middot; {{ $note->created_at->format('d/m/Y H:i') }}</p>
                    <p class="text-sm text-gray-800 whitespace-pre-line">{{ $note->note }}</p>
                </div>
            @endforeach
        @endif
    </div>
</form>
