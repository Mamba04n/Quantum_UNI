<header class="bg-slate-900 px-8 py-8 text-white">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest {{ $isFromAdmin ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-300' }}">
                {{ $isFromAdmin ? 'Admin Requiere Cambios' : 'Cliente Requiere Cambios' }}
            </span>
            <h3 class="mt-4 text-3xl font-black">{{ $selectedQuote->quote_to_name }}</h3>
            <p class="mt-2 text-sm font-medium text-slate-300">Ref: {{ $selectedQuote->reference }}</p>
        </div>
    </div>
</header>
