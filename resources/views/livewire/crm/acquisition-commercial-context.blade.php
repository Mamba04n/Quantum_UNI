<section class="mt-6 border-t border-gray-100 pt-5">
    <h3 class="mb-3 text-sm font-semibold">Análisis de contexto</h3>
    <p class="mb-4 text-xs text-gray-500">Completa estos datos progresivamente. Para calificar se requiere necesidad, interés, al menos un dato de contexto y un criterio valorado.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach(['need' => 'Necesidad detectada', 'products' => 'Productos de interés', 'services' => 'Servicios de interés'] as $key => $label)<label class="text-sm font-medium text-gray-700">{{ $label }}<textarea wire:model="form.{{ $key }}" rows="2" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea></label>@endforeach
        @foreach(config('acquisition.context_fields') as $key => $label)<label class="text-sm font-medium text-gray-700">{{ $label }}<textarea wire:model="form.context.{{ $key }}" rows="2" maxlength="3000" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea></label>@endforeach
    </div>
</section>
<section class="mt-6 border-t border-gray-100 pt-5">
    <div class="mb-3 flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">Maquinaria / activos conocidos</h3><button type="button" wire:click="addMachine" class="shrink-0 text-sm font-semibold text-slate-700">+ Agregar equipo</button></div>
    @forelse($form['machines'] ?? [] as $index => $machine)
        <div wire:key="machine-{{ $index }}" class="mb-5 grid gap-3 border-b border-gray-100 pb-4 last:border-0 sm:grid-cols-3">
            <x-acquisition-field label="Tipo *" model="form.machines.{{ $index }}.type"/>
            <x-acquisition-field label="Marca" model="form.machines.{{ $index }}.brand"/>
            <x-acquisition-field label="Modelo" model="form.machines.{{ $index }}.model"/>
            <x-acquisition-field label="Serie" model="form.machines.{{ $index }}.serial"/>
            <x-acquisition-field label="Motor" model="form.machines.{{ $index }}.engine"/>
            <x-acquisition-field label="Cantidad *" model="form.machines.{{ $index }}.quantity" type="number" min="1"/>
            <label class="text-sm font-medium text-gray-700 sm:col-span-3">Observaciones<textarea wire:model="form.machines.{{ $index }}.notes" rows="2" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea></label>
            <button type="button" wire:click="removeMachine({{ $index }})" class="justify-self-start text-xs text-red-800">Quitar equipo</button>
        </div>
    @empty <p class="text-sm text-gray-500">Sin activos registrados. Esta información es opcional.</p>@endforelse
</section>
<section class="mt-6 border-t border-gray-100 pt-5">
    <h3 class="mb-2 text-sm font-semibold">Clasificación y criterios comerciales</h3>
    <p class="mb-4 text-xs text-gray-500">Los criterios orientan al vendedor; no calculan una clasificación automática.</p>
    <fieldset @disabled(!auth()->user()->hasPermissionTo('prospects.classify'))>
        <div class="grid gap-4 sm:grid-cols-2">@foreach(config('acquisition.criteria') as $key => $label)<label class="text-sm font-medium text-gray-700">{{ $label }}<select wire:model="form.classification_criteria.{{ $key }}" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Sin evaluar</option>@foreach(config('acquisition.levels') as $level)<option>{{ $level }}</option>@endforeach</select></label>@endforeach</div>
        <label class="mt-4 block text-sm font-medium text-gray-700">Clasificación manual<select wire:model="form.classification" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="">Sin clasificar</option>@foreach(config('acquisition.classes') as $key => $label)<option value="{{ $key }}">{{ $key }} — {{ $label }}</option>@endforeach</select></label>
    </fieldset>
</section>
<section class="mt-6 border-t border-gray-100 pt-5">
    <h3 class="mb-3 text-sm font-semibold">Seguimiento</h3>
    <p class="mb-3 text-sm text-gray-500">Último contacto: {{ $selected->last_contact_at?->format('d/m/Y H:i') ?? 'Sin registrar' }} {{ $selected->last_contact_channel }}</p>
    <div class="grid gap-4 sm:grid-cols-2"><x-acquisition-field label="Próxima acción" model="form.next_action"/><x-acquisition-field label="Próximo seguimiento" model="form.next_contact_at" type="date"/></div>
</section>
