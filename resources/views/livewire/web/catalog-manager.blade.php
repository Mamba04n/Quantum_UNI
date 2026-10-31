<div class="p-6 font-sans sm:p-8">
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">
                @if($viewMode === 'form')
                    {{ $catalog_id ? 'Editar Catálogo' : 'Nuevo Catálogo' }}
                @else
                    Catálogos Digitales
                @endif
            </h2>
            <p class="mt-0.5 text-sm text-gray-500">
                Documentos técnicos y comerciales publicados para clientes.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($viewMode == 'form')
                <button wire:click="showList" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                    Descartar
                </button>
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save" data-testid="catalog-save-button" class="flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:cursor-wait disabled:opacity-60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span wire:loading.remove wire:target="save">Guardar catálogo</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @else
                <div class="relative w-full sm:w-auto">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input wire:model.live.debounce.500ms="search" type="text" placeholder="Buscar catálogo..." class="w-full rounded-xl border-transparent bg-gray-50 py-2 pl-9 pr-3 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200 sm:w-72">
                </div>

                <button wire:click="create" data-testid="catalog-create-button" class="flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Nuevo Catálogo
                </button>
            @endif
        </div>
    </div>

    @if(session()->has('message'))
        <div class="mb-6 flex items-center p-4 text-sm text-gray-800 rounded-xl bg-gray-50 border border-gray-100 shadow-sm" role="alert">
            <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="font-semibold">{{ session('message') }}</span>
        </div>
    @endif

    <div>
        @if($viewMode === 'form')
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Información del documento</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Defina cómo se identificará el catálogo y adjunte el archivo que estará disponible en la web.</p>
                </div>
                <div class="grid lg:grid-cols-12">
                    <div class="grid content-start gap-4 p-5 sm:grid-cols-2 lg:col-span-8">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Nombre del catálogo</label>
                        <input wire:model="nombre" data-testid="catalog-name-input" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. Manual de operación 420F">
                        @error('nombre') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Categoría</label>
                        <input wire:model="categoria" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. Retroexcavadoras o motores">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Descripción pública</label>
                        <textarea wire:model="descripcion" rows="4" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Resumen del contenido y utilidad del documento para el cliente."></textarea>
                        @error('descripcion') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    </div>
                    <aside class="border-t border-gray-100 bg-gray-50/50 p-5 lg:col-span-4 lg:border-l lg:border-t-0">
                        <h3 class="text-sm font-semibold text-gray-900">Archivo y publicación</h3>
                        <p class="mt-0.5 text-xs leading-5 text-gray-500">Formatos admitidos: PDF, Word, Excel y PowerPoint, hasta 10 MB.</p>
                        <div class="mt-4 flex justify-center rounded-xl border border-dashed border-gray-300 bg-white px-4 py-5">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-7 w-7 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="file-upload" class="relative cursor-pointer rounded-md bg-white font-semibold text-slate-800 hover:text-slate-950 focus-within:outline-none">
                                        <span>Seleccionar archivo</span>
                                        <input id="file-upload" wire:model="file" data-testid="catalog-file-input" type="file" class="sr-only">
                                    </label>
                                </div>
                            </div>
                        </div>
                        @if($file)
                            <div class="mt-2 text-xs text-emerald-600 font-bold flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Archivo cargado: {{ $file->getClientOriginalName() }}
                            </div>
                        @elseif($archivo)
                            <div class="mt-3 break-all text-xs font-medium text-emerald-700">Archivo actual: {{ $archivo }}</div>
                        @endif
                        @error('file') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-4 flex items-start gap-2 border-t border-gray-200 pt-4">
                        <input wire:model="destacado" type="checkbox" id="destacado" class="w-4 h-4 text-gray-900 border-gray-300 rounded focus:ring-gray-300">
                        <label for="destacado" class="cursor-pointer text-sm text-gray-700"><span class="font-semibold">Catálogo destacado</span><span class="mt-0.5 block text-xs text-gray-500">Se mostrará antes que los demás documentos.</span></label>
                    </div>
                    </aside>
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Catálogo / Documento</th>
                                <th class="px-5 py-3 font-semibold">Categoría</th>
                                <th class="px-5 py-3 font-semibold text-center">Tipo</th>
                                <th class="px-5 py-3 font-semibold text-center">Estado</th>
                                <th class="px-5 py-3 font-semibold text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($catalogos as $cat)
                                <tr class="hover:bg-gray-50/50 transition-colors group">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707L19 9.414V19a2 2 0 01-2 2z"></path></svg>
                                            </div>
                                            <div>
                                                <span class="font-bold text-gray-900 block">{{ $cat->nombre }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">{{ $cat->categoria ?: 'General' }}</td>
                                    <td class="px-5 py-3 text-center uppercase font-bold text-[10px] text-gray-400">
                                        {{ pathinfo($cat->archivo, PATHINFO_EXTENSION) ?: 'N/A' }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        @if($cat->destacado)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase">Destacado</span>
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button wire:click="edit({{ $cat->id }})" class="text-gray-900 font-bold text-xs hover:underline uppercase">Editar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-20 text-center text-gray-400">
                                        <p>No hay catálogos registrados.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
