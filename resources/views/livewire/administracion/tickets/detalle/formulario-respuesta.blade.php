<form wire:submit="sendReply" class="relative mt-2">
    <div class="flex items-end gap-2 bg-slate-50 border border-slate-200 rounded-2xl p-2 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 transition-all shadow-sm">

        <div class="relative shrink-0">
            <label class="cursor-pointer p-2 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-200 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                <input wire:model="replyAttachment" type="file" class="hidden">
            </label>
        </div>

        <textarea wire:model="replyMessage" rows="1" class="w-full resize-y min-h-[40px] max-h-[120px] border-0 bg-transparent text-sm py-2.5 px-1 focus:ring-0 text-slate-700 placeholder-slate-400" placeholder="Escribe un mensaje..."></textarea>

        <button type="submit" wire:loading.attr="disabled" class="shrink-0 p-2 flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition-colors disabled:opacity-50">
            <svg class="w-5 h-5 translate-x-0.5 -translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
        </button>
    </div>

    @if($replyAttachment)
        <div class="mt-2 flex items-center gap-2 bg-indigo-50 text-indigo-700 text-xs font-bold px-3 py-1.5 rounded-lg border border-indigo-100 w-max">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Archivo adjunto listo
            <button type="button" wire:click="$set('replyAttachment', null)" class="ml-2 text-indigo-400 hover:text-indigo-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    @endif
    @error('replyMessage') <p class="text-[10px] text-rose-600 font-bold mt-1 ml-1">{{ $message }}</p> @enderror
    @error('replyAttachment') <p class="text-[10px] text-rose-600 font-bold mt-1 ml-1">{{ $message }}</p> @enderror
</form>
