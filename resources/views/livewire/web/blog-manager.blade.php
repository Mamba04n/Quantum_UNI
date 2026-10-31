<div class="p-6 font-sans sm:p-8">
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">
                @if($viewMode === 'form')
                    {{ $post_id ? 'Editar Artículo' : 'Nuevo Artículo' }}
                @else
                    Blog Técnico
                @endif
            </h2>
            <p class="mt-0.5 text-sm text-gray-500">
                Artículos técnicos, compatibilidades y referencias para clientes.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($viewMode == 'form')
                <button wire:click="showList" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                    Descartar
                </button>
                <button wire:click="save" wire:loading.attr="disabled" wire:target="save" data-testid="content-blog-save-button" class="flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:cursor-wait disabled:opacity-60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span wire:loading.remove wire:target="save">Guardar artículo</span>
                    <span wire:loading wire:target="save">Guardando...</span>
                </button>
            @else
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input wire:model.live.debounce.500ms="search" type="text" placeholder="Buscar artículo..." class="w-full rounded-xl border-transparent bg-gray-50 py-2 pl-9 pr-3 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200 sm:w-72">
                </div>

                <button wire:click="create" data-testid="content-blog-create-button" class="flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Nuevo Artículo
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
                    <h3 class="text-base font-semibold text-gray-900">Contenido del artículo</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Organice el contenido para que sea fácil de encontrar por máquina, modelo o número de parte.</p>
                </div>
                <div class="p-5">

                <div class="mb-5 grid items-start gap-5 md:grid-cols-12">
                    <div class="w-28 shrink-0 md:col-span-2">
                        <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400">
                            @if($photo)
                                <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                            @elseif($image_url)
                                <img src="{{ $image_url }}" class="w-full h-full object-cover">
                            @elseif($post_id && \Illuminate\Support\Facades\Schema::hasTable('tech_blog_posts') && ($currentImg = DB::table('tech_blog_posts')->where('id', $post_id)->value('imagen')))
                                <img src="{{ \App\Support\StorefrontUrl::blogImage($currentImg) }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            @endif
                        </div>
                        <div class="mt-3 space-y-2">
                            <input type="file" wire:model="photo" class="hidden" id="blog-photo">
                            <label for="blog-photo" class="block w-full cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">Seleccionar imagen</label>
                            <input type="text" wire:model.blur="image_url" placeholder="URL de imagen" class="w-full rounded-lg border-transparent bg-gray-50 px-2.5 py-2 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200">
                        </div>
                    </div>

                    <div class="w-full md:col-span-10">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Título del artículo</label>
                        <input wire:model.blur="titulo" data-testid="content-blog-title-input" type="text" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Ej. Mantenimiento de retroexcavadora 320D">
                        @error('titulo') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                        <div class="mt-4">
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Dirección web</label>
                            <div class="flex items-center rounded-xl bg-gray-50 px-3 focus-within:bg-white focus-within:ring-2 focus-within:ring-slate-200">
                                <span class="shrink-0 text-xs text-gray-400">/blog/</span>
                                <input wire:model="slug" type="text" class="w-full border-0 bg-transparent px-1 py-2.5 text-sm text-gray-600 focus:ring-0" placeholder="url-del-articulo">
                            </div>
                        </div>
                        @error('slug') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Información técnica</h4>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @foreach([['maquina', 'Máquina', 'Ej. Retroexcavadora'], ['modelo', 'Modelo', 'Ej. 320D'], ['pieza', 'Pieza o referencia', 'Ej. Bomba hidráulica'], ['numero_parte', 'Número de parte', 'Referencia del fabricante']] as [$field, $label, $placeholder])
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">{{ $label }}</label>
                                        <input wire:model="{{ $field }}" type="text" placeholder="{{ $placeholder }}" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-gray-100 pt-5 lg:col-span-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Categoría</label>
                            <input wire:model="categoria" type="text" placeholder="Ej. Motores o hidráulica" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700">Resumen para el listado</label>
                            <textarea wire:model="resumen" rows="4" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2.5 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Explique brevemente qué aprenderá el lector."></textarea>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-5 lg:col-span-12">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Contenido detallado</label>
                        <textarea wire:model="contenido" data-testid="content-blog-body-input" rows="10" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-3 font-sans text-sm leading-6 focus:bg-white focus:ring-2 focus:ring-slate-200" placeholder="Desarrolle aquí la información técnica del artículo..."></textarea>
                        @error('contenido') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                    </div>
                </div></div>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Artículo</th>
                                <th class="px-5 py-3 font-semibold">Referencia técnica</th>
                                <th class="px-5 py-3 font-semibold">Categoría</th>
                                <th class="px-5 py-3 font-semibold">Fecha</th>
                                <th class="px-5 py-3 text-right font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                @forelse($posts as $post)
                    <tr wire:click="edit({{ $post->id }})" class="cursor-pointer transition-colors hover:bg-gray-50/50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-12 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                            @if($post->imagen)
                                <img src="{{ \App\Support\StorefrontUrl::blogImage($post->imagen) }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-gray-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM14 4v4h4"></path></svg>
                                </div>
                            @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="max-w-md truncate font-semibold text-gray-900">{{ $post->titulo }}</p>
                                    <p class="max-w-md truncate text-xs text-gray-500">{{ $post->resumen ?: Str::limit(strip_tags($post->contenido), 90) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            <p>{{ collect([$post->maquina, $post->modelo])->filter()->implode(' · ') ?: 'Sin equipo' }}</p>
                            <p class="font-mono text-xs text-gray-400">{{ $post->numero_parte ?: 'Sin número de parte' }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $post->categoria ?: 'General' }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ date('d/m/Y', strtotime($post->created_at)) }}</td>
                        <td class="px-5 py-3 text-right"><button wire:click.stop="edit({{ $post->id }})" class="text-xs font-semibold text-slate-700 hover:text-slate-950">Editar</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400">No hay artículos técnicos registrados.</td></tr>
                @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
