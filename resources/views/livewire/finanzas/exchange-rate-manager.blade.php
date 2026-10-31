<div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <p class="text-sm font-medium text-slate-500">Finanzas</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Tasas de cambio</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $this->canAdminister() ? 'Administración central de las monedas utilizadas en ventas, compras y finanzas.' : 'Consulta las tasas vigentes o envía una propuesta de cambio para aprobación administrativa.' }}</p>
            <p class="mt-1 text-xs text-slate-500">Fuente automática diaria: <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener noreferrer" class="underline decoration-slate-300 underline-offset-2 hover:text-slate-700">ExchangeRate-API</a>.</p>
        </div>

        @if(session('message'))
            <div class="border-l-4 border-emerald-600 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm">{{ session('message') }}</div>
        @endif

        <form wire:submit="saveRate" class="grid gap-5 border border-slate-200 bg-white p-6 shadow-sm md:grid-cols-4">
            <div class="md:col-span-4 flex flex-col justify-between gap-2 border-b border-slate-200 pb-5 sm:flex-row sm:items-end">
                <div><p class="text-base font-semibold text-slate-900">Tasa vigente</p><p class="mt-1 text-sm text-slate-500">Valor global aplicado por el sistema.</p></div>
                <p class="text-xl font-semibold tabular-nums text-slate-950">1 {{ $baseCurrency }} = C$ {{ $rate !== '' ? number_format((float) $rate, 6) : '—' }}</p>
            </div>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Moneda origen</span>
                <select wire:model.live="baseCurrency" class="mt-2 w-full rounded-md border-slate-300 text-sm font-medium">
                    @foreach($currencies->where('code', '!=', 'NIO')->where('is_active', true) as $currency)
                        <option value="{{ $currency->code }}">{{ $currency->code }} · {{ $currency->name }}</option>
                    @endforeach
                </select>
                @error('baseCurrency') <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Moneda destino</span>
                <input value="NIO" disabled class="mt-2 w-full rounded-md border-slate-300 bg-slate-100 text-sm font-medium text-slate-600">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Tasa</span>
                <input type="number" step="0.000001" min="0.000001" wire:model="rate" class="mt-2 w-full rounded-md border-slate-300 text-sm font-medium tabular-nums" placeholder="36.850000">
                @error('rate') <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Fecha efectiva</span>
                <input type="date" max="{{ now()->toDateString() }}" wire:model="rateDate" class="mt-2 w-full rounded-md border-slate-300 text-sm font-medium">
                @error('rateDate') <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span> @enderror
            </label>
            @unless($this->canAdminister())
                <label class="block md:col-span-4"><span class="text-sm font-medium text-slate-700">Motivo de la solicitud</span><textarea wire:model="changeReason" rows="3" class="mt-2 w-full rounded-md border-slate-300 text-sm" placeholder="Explica por qué debe cambiarse la tasa."></textarea>@error('changeReason') <span class="mt-1 block text-xs font-medium text-red-700">{{ $message }}</span> @enderror</label>
            @endunless
            <div class="md:col-span-4 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
                @if($this->canAdminister() && $currencies->firstWhere('code', $baseCurrency)?->automatic_updates_enabled)
                    <button type="button" wire:click="refreshAutomaticRate" wire:loading.attr="disabled" class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60">Actualizar automáticamente</button>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">{{ $this->canAdminister() ? 'Registrar tasa' : 'Solicitar aprobación' }}</button>
            </div>
        </form>

        @if($this->canAdminister())
        <div class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Monedas habilitadas</h2>
                    <p class="mt-1 text-sm text-slate-500">La actualización programada se ejecuta diariamente para las monedas habilitadas.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($currencies as $currency)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $currency->code }} · {{ $currency->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">Símbolo: {{ $currency->symbol }}</p>
                            </div>
                            @if($currency->code === 'NIO')
                                <span class="text-xs font-medium text-slate-500">Moneda base</span>
                            @elseif($currency->is_fixed)
                                <span class="text-xs font-medium text-slate-600">Tasa fija</span>
                            @else
                                <div class="flex items-center gap-2">@if($currency->automatic_updates_enabled)<select wire:change="setAutomaticInterval({{ $currency->id }}, $event.target.value)" class="rounded-md border-slate-300 py-2 text-xs"><option value="24" @selected($currency->automatic_update_interval_hours === 24)>Cada 24 h</option><option value="48" @selected($currency->automatic_update_interval_hours === 48)>Cada 48 h</option></select>@endif<button type="button" wire:click="toggleAutomatic({{ $currency->id }})" class="rounded-md border px-3 py-2 text-xs font-semibold {{ $currency->automatic_updates_enabled ? 'border-slate-700 bg-slate-700 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">Automatización {{ $currency->automatic_updates_enabled ? 'activa' : 'inactiva' }}</button></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <form wire:submit="addCurrency" class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Agregar moneda</h2>
                <p class="mt-1 text-sm text-slate-500">Utiliza el código internacional ISO 4217.</p>
                <div class="mt-4 space-y-4">
                    <label class="block"><span class="text-sm font-medium text-slate-700">Código ISO</span><input wire:model="newCurrencyCode" maxlength="3" class="mt-2 w-full rounded-md border-slate-300 text-sm font-medium uppercase" placeholder="GBP">@error('newCurrencyCode') <span class="mt-1 block text-xs font-medium text-red-700">{{ $message }}</span> @enderror</label>
                    <label class="block"><span class="text-sm font-medium text-slate-700">Nombre</span><input wire:model="newCurrencyName" class="mt-2 w-full rounded-md border-slate-300 text-sm" placeholder="Libra esterlina">@error('newCurrencyName') <span class="mt-1 block text-xs font-medium text-red-700">{{ $message }}</span> @enderror</label>
                    <label class="block"><span class="text-sm font-medium text-slate-700">Símbolo</span><input wire:model="newCurrencySymbol" class="mt-2 w-full rounded-md border-slate-300 text-sm" placeholder="£">@error('newCurrencySymbol') <span class="mt-1 block text-xs font-medium text-red-700">{{ $message }}</span> @enderror</label>
                    <label class="flex items-center gap-3 border border-slate-200 px-3 py-3 text-sm text-slate-700"><input type="checkbox" wire:model="newCurrencyAutomatic" class="rounded border-slate-300"> Habilitar actualización automática</label>
                    <button class="w-full rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Agregar moneda</button>
                </div>
            </form>
        </div>
        @endif

        @if($changeRequests->isNotEmpty())
            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-base font-semibold text-slate-900">{{ $this->canAdminister() ? 'Solicitudes de cambio' : 'Mis solicitudes' }}</h2></div>
                <div class="divide-y divide-slate-100">
                    @foreach($changeRequests as $request)
                        <div class="flex flex-col justify-between gap-4 px-5 py-4 md:flex-row md:items-center">
                            <div><p class="font-semibold text-slate-900">{{ $request->base_currency }} → {{ $request->quote_currency }} · {{ number_format((float) $request->proposed_rate, 6) }}</p><p class="mt-1 text-sm text-slate-600">{{ $request->reason }}</p><p class="mt-1 text-xs text-slate-500">{{ $request->requester?->name }} · {{ $request->created_at->format('d/m/Y H:i') }} · {{ match($request->status) { 'approved' => 'Aprobada', 'rejected' => 'Rechazada', default => 'Pendiente' } }}</p></div>
                            @if($this->canAdminister() && $request->status === 'pending')<div class="flex gap-2"><button type="button" wire:click="rejectRequest({{ $request->id }})" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Rechazar</button><button type="button" wire:click="approveRequest({{ $request->id }})" wire:confirm="¿Aprobar esta tasa y convertirla en el valor global vigente?" class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Aprobar</button></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-base font-semibold text-slate-900">Historial reciente</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-600"><tr><th class="px-5 py-3">Par</th><th class="px-5 py-3">Tasa</th><th class="px-5 py-3">Fecha / vence</th><th class="px-5 py-3">Origen</th><th class="px-5 py-3">Aprobador</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rates as $exchangeRate)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ $exchangeRate->base_currency }} → {{ $exchangeRate->quote_currency }}</td>
                                <td class="px-5 py-3 font-mono font-medium tabular-nums">{{ $exchangeRate->rate }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $exchangeRate->rate_date?->format('d/m/Y') }} · {{ $exchangeRate->expires_at?->format('d/m/Y') ?: 'sin definir' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $exchangeRate->source_type?->label() ?: $exchangeRate->source }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $exchangeRate->approver?->name ?: 'Automática / histórica' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center font-bold text-slate-400">No hay tasas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
