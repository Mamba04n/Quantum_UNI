@if (session('message'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 5200)"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-3"
        x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-3"
        class="pointer-events-none fixed right-4 top-20 z-50 w-[calc(100vw-2rem)] max-w-md sm:right-6"
    >
        <div class="pointer-events-auto rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-xl ring-1 ring-black/5">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                    <span class="text-xs font-black">OK</span>
                </div>
                <p class="min-w-0 flex-1 leading-5">{{ session('message') }}</p>
                <button type="button" x-on:click="show = false" class="-mr-1 rounded-md px-1.5 py-0.5 text-xs font-black text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    X
                </button>
            </div>
        </div>
    </div>
@endif

@if ($errors->any())
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 7000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-3"
        x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-3"
        class="pointer-events-none fixed right-4 top-20 z-50 w-[calc(100vw-2rem)] max-w-md sm:right-6"
    >
        <div class="pointer-events-auto rounded-lg border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-xl ring-1 ring-black/5">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-700">
                    <span class="text-xs font-black">!</span>
                </div>
                <p class="min-w-0 flex-1 leading-5">{{ $errors->first() }}</p>
                <button type="button" x-on:click="show = false" class="-mr-1 rounded-md px-1.5 py-0.5 text-xs font-black text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    X
                </button>
            </div>
        </div>
    </div>
@endif
