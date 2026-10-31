<section class="xl:col-span-2 bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">Modulos exportables</h3>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-0 divide-y md:divide-y-0 md:divide-x divide-gray-100">
        @foreach(collect($modules)->chunk(ceil(count($modules) / 2)) as $chunk)
            <div class="divide-y divide-gray-100">
                @foreach($chunk as $exportModule)
                    <button type="button"
                            wire:click="$set('module', '{{ $exportModule['key'] }}')"
                            class="w-full flex items-center justify-between gap-4 px-5 py-3 text-left hover:bg-gray-50 transition-colors {{ $module === $exportModule['key'] ? 'bg-emerald-50' : '' }}">
                        <span>
                            <span class="block text-sm font-bold {{ $module === $exportModule['key'] ? 'text-emerald-800' : 'text-gray-800' }}">
                                {{ $exportModule['label'] }}
                            </span>
                            <span class="block text-xs text-gray-400">{{ $exportModule['key'] }}</span>
                        </span>
                        <span class="text-xs font-black px-2 py-1 rounded-lg {{ $module === $exportModule['key'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $exportModule['count'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
