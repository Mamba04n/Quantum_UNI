@if($showBulkModal ?? $showBulkImportModal ?? false)
    @php
        $previewItems = $bulkPreviewItems ?? $bulkImportPreview ?? [];
        $activeMode = $bulkMode ?? 'file';
        $uploadedFile = $bulkExcelFile ?? null;
        $closeAction = isset($showBulkModal) && $showBulkModal ? 'closeBulkModal' : 'closeBulkImportModal';
        $applyAction = isset($showBulkModal) && $showBulkModal ? 'applyBulkItems' : 'applyBulkImportToEditableItems';
        $textModel = isset($showBulkModal) && $showBulkModal ? 'bulkPasteText' : 'bulkImportText';
        $fileModel = 'bulkExcelFile';
        $clearFileAction = 'clearBulkFile';
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm">
        <div class="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl border border-slate-200/80 overflow-hidden flex flex-col max-h-[92vh]">
            
            {{-- Header con diseño corporativo --}}
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black tracking-tight text-slate-900">Carga Masiva de Partidas</h3>
                        <p class="text-xs font-medium text-slate-500">Importa un archivo Excel (.xlsx, .csv) o pega las columnas directamente para autocompletar la cotización.</p>
                    </div>
                </div>
                <button type="button" wire:click="{{ $closeAction }}" class="rounded-xl p-2 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 transition">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Navegación por pestañas y descarga de plantilla --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-white px-6 py-2.5">
                <div class="flex items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl">
                    <button
                        type="button"
                        wire:click="setBulkMode('file')"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $activeMode === 'file' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                    >
                        <svg class="w-4 h-4 {{ $activeMode === 'file' ? 'text-emerald-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Subir Archivo Excel / CSV
                    </button>
                    <button
                        type="button"
                        wire:click="setBulkMode('text')"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $activeMode === 'text' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                    >
                        <svg class="w-4 h-4 {{ $activeMode === 'text' ? 'text-indigo-600' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Pegar Texto Copiado
                    </button>
                </div>

                {{-- Botón de descarga de plantilla oficial --}}
                <a
                    href="{{ route('sales.quotations.template.excel-items') }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/70 px-3.5 py-1.5 text-xs font-bold text-emerald-800 hover:bg-emerald-100 hover:border-emerald-300 transition shadow-sm"
                >
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar Plantilla Excel (.xlsx)
                </a>
            </div>

            {{-- Cuerpo del Modal --}}
            <div class="p-6 space-y-5 overflow-y-auto flex-1 text-xs">

                {{-- MODO 1: SUBIR ARCHIVO EXCEL --}}
                @if($activeMode === 'file')
                    <div class="space-y-3">
                        <div class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/60 p-6 text-center hover:bg-slate-50/90 transition group">
                            
                            {{-- Loading indicator Livewire --}}
                            <div wire:loading wire:target="{{ $fileModel }}" class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-2xl bg-white/90 backdrop-blur-xs">
                                <svg class="h-8 w-8 animate-spin text-slate-800" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <p class="mt-2 text-xs font-bold text-slate-800">Procesando y leyendo archivo Excel...</p>
                            </div>

                            @if(!$uploadedFile)
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm border border-slate-200 text-slate-600 mb-3 group-hover:scale-105 transition">
                                    <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <label class="cursor-pointer">
                                    <span class="rounded-lg bg-slate-900 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-black transition">
                                        Seleccionar Archivo Excel
                                    </span>
                                    <input
                                        type="file"
                                        wire:model="{{ $fileModel }}"
                                        accept=".xlsx,.xls,.csv,.txt"
                                        class="hidden"
                                    >
                                </label>
                                <p class="mt-2 text-[11px] font-medium text-slate-500">o arrastra y suelta tu archivo aquí</p>
                                <p class="mt-1 text-[10px] text-slate-400 font-mono">Formatos soportados: .xlsx, .xls, .csv (Máx. 10MB)</p>
                            @else
                                <div class="w-full flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                                    <div class="flex items-center gap-3 text-left">
                                        <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900 text-xs">{{ $uploadedFile->getClientOriginalName() }}</p>
                                            <p class="text-[10px] text-slate-500 font-mono">{{ number_format($uploadedFile->getSize() / 1024, 1) }} KB &middot; Archivo cargado</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="{{ $clearFileAction }}"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                        title="Quitar archivo"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            @endif
                        </div>
                        @error($fileModel)
                            <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- MODO 2: PEGAR TEXTO / PORTAPAPELES --}}
                @if($activeMode === 'text')
                    <div class="space-y-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-slate-700 text-[11px] leading-relaxed">
                            <p class="font-bold text-slate-900 mb-1 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Formato de columnas para pegar:
                            </p>
                            <p class="text-slate-600">
                                Copia las celdas en tu Excel y pégalas en el recuadro. Orden sugerido de columnas:
                                <span class="font-mono text-slate-900 bg-white px-1.5 py-0.5 rounded border border-slate-200">CÓDIGO &nbsp;|&nbsp; DESCRIPCIÓN &nbsp;|&nbsp; CANTIDAD &nbsp;|&nbsp; UNIDAD &nbsp;|&nbsp; MARCA</span>
                            </p>
                        </div>

                        <div>
                            <textarea
                                wire:model.live.debounce.300ms="{{ $textModel }}"
                                rows="5"
                                placeholder="Ejemplo:&#10;5580101693	Eje diferencial Trasero	1	PZA	Caterpillar&#10;5580101692	Eje diferencial Delantero	2	PZA	Caterpillar&#10;1R-0716	Filtro de combustible	4	UND	CAT"
                                class="w-full font-mono text-xs rounded-xl border border-slate-300 p-3 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition"
                            ></textarea>
                        </div>
                    </div>
                @endif

                {{-- VISTA PREVIA DE PARTIDAS DETECTADAS --}}
                @if(!empty($previewItems))
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Vista previa: {{ count($previewItems) }} partida(s) listas para insertar
                            </h4>
                            <span class="text-[11px] font-bold text-slate-500">
                                Total piezas: <strong class="text-slate-900">{{ collect($previewItems)->sum('quantity') }}</strong>
                            </span>
                        </div>
                        <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xs">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-center w-8">#</th>
                                        <th class="px-3 py-2 w-32">Código</th>
                                        <th class="px-3 py-2 min-w-[160px]">Descripción</th>
                                        <th class="px-3 py-2 text-right w-14">Cant.</th>
                                        <th class="px-3 py-2 w-16">Unidad</th>
                                        <th class="px-3 py-2 w-24">Marca</th>
                                        <th class="px-3 py-2 w-36">Catálogo / Stock</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                    @foreach($previewItems as $idx => $pItem)
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-3 py-2 text-slate-400 font-mono text-center">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-2 font-bold font-mono text-slate-900">{{ $pItem['part_code'] }}</td>
                                            <td class="px-3 py-2 text-slate-800">{{ $pItem['part_name'] }}</td>
                                            <td class="px-3 py-2 text-right font-black text-slate-900">{{ $pItem['quantity'] }}</td>
                                            <td class="px-3 py-2 text-slate-500 font-mono">{{ $pItem['unit'] }}</td>
                                            <td class="px-3 py-2 text-slate-500">{{ $pItem['part_brand'] ?: '—' }}</td>
                                            <td class="px-3 py-2">
                                                @if(!empty($pItem['is_in_inventory']))
                                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800" title="Producto encontrado en catálogo. Stock actual: {{ $pItem['product_stock'] }}">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                        En catálogo ({{ $pItem['product_stock'] }})
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-500" title="No registrado previamente en catálogo de productos. Se creará como partida libre.">
                                                        Captura libre
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-6 py-3.5">
                <button type="button" wire:click="{{ $closeAction }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-xs">
                    Cancelar
                </button>
                <button
                    type="button"
                    wire:click="{{ $applyAction }}"
                    @if(empty($previewItems)) disabled @endif
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-black text-white shadow-sm hover:bg-black disabled:opacity-40 disabled:cursor-not-allowed transition"
                >
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Insertar {{ count($previewItems) ?: '' }} Partidas en la Cotización
                </button>
            </div>
        </div>
    </div>
@endif
