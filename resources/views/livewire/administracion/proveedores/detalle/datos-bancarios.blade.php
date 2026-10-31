@if($this->canSeeBankData())
    <section class="overflow-hidden rounded-lg border border-slate-200">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-base font-semibold text-slate-900">Registrar cuenta bancaria</h2><p class="mt-1 text-sm text-slate-500">Información sensible visible únicamente para perfiles autorizados.</p></div>
        <form wire:submit="addBankAccount" class="grid gap-4 bg-slate-50 p-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach([['bankName','Banco'],['accountHolder','Titular'],['accountNumber','Número de cuenta'],['accountType','Tipo de cuenta'],['currency','Moneda'],['swiftIban','SWIFT / IBAN'],['bankCountry','País del banco'],['bankNotes','Observaciones']] as [$property,$label])
                <label class="{{ $property === 'bankNotes' ? 'md:col-span-2' : '' }}"><span class="mb-1 block text-xs font-medium text-slate-700">{{ $label }}</span><input wire:model="{{ $property }}" class="h-10 w-full rounded-lg border-slate-300 bg-white text-sm {{ $property === 'currency' ? 'uppercase' : '' }}"></label>
            @endforeach
            <div class="flex items-end justify-end"><button class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">Registrar cuenta</button></div>
        </form>
    </section>
    <section class="mt-5 overflow-hidden rounded-lg border border-slate-200">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="text-sm font-semibold text-slate-900">Cuentas registradas</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse($selectedSupplier->bankAccounts as $account)
                <div class="grid gap-2 px-5 py-4 sm:grid-cols-[1fr_1fr_auto] sm:items-center"><div><p class="text-sm font-semibold text-slate-900">{{ $account->bank_name }}</p><p class="text-xs text-slate-500">{{ $account->account_holder }}</p></div><div><p class="text-sm text-slate-800">{{ $account->account_number }}</p><p class="text-xs text-slate-500">{{ $account->currency }} · {{ $account->swift_iban ?: 'Sin SWIFT / IBAN' }}</p></div><button wire:click="deleteBankAccount({{ $account->id }})" class="text-xs font-medium text-slate-500 hover:text-rose-700">Eliminar</button></div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">No hay cuentas bancarias registradas.</p>
            @endforelse
        </div>
    </section>
@else
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-5"><p class="text-sm font-semibold text-amber-900">Información bancaria protegida</p><p class="mt-1 text-sm text-amber-800">Tu perfil no tiene autorización para consultar cuentas bancarias.</p></div>
@endif
