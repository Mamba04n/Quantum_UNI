<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black text-slate-950">Datos del documento</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Cotizar / ordenar a nombre de</span>
            <input wire:model="quote_to_name" class="mt-1 w-full rounded-xl border-slate-200 text-sm" placeholder="Quantum Soluciones S.A.">
            @error('quote_to_name') <span class="text-xs font-bold text-rose-700">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Estado</span>
            <input value="{{ str($document->status)->headline() }}" disabled class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
        </label>
    </div>
</section>
