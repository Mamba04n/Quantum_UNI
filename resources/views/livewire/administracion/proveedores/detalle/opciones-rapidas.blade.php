<div class="mt-4 flex flex-wrap gap-2">
    @foreach($options as $option)
        <button wire:click="$set('{{ $property }}', '{{ $option }}')" class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">{{ $option }}</button>
    @endforeach
</div>
