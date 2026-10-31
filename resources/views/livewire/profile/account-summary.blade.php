<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    #[Computed]
    public function account(): \App\Models\User
    {
        return Auth::user()->loadMissing('roles');
    }

    #[On('profile-updated')]
    public function refreshAccount(): void
    {
        Auth::user()->refresh();
    }
}; ?>

<section class="space-y-4" aria-label="Resumen de tu cuenta">
    <h2 class="text-base font-semibold text-gray-900">Tu cuenta</h2>
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-sm font-semibold text-[#6f1220]" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($this->account->name), 0, 2)) }}</span>
        <p class="min-w-0 break-words text-sm font-semibold text-gray-900">{{ $this->account->name }}</p>
    </div>
    <dl class="space-y-3 text-sm">
        <div><dt class="text-gray-500">Rol</dt><dd class="mt-0.5 text-gray-900">{{ $this->account->roles->pluck('name')->join(', ') ?: 'Sin rol asignado' }}</dd></div>
        <div><dt class="text-gray-500">Estado de la cuenta</dt><dd class="mt-0.5 {{ $this->account->is_active ? 'text-teal-700' : 'text-gray-500' }}">{{ $this->account->is_active ? 'Activa' : 'Inactiva' }}</dd></div>
        <div><dt class="text-gray-500">Verificación del correo</dt><dd class="mt-0.5 {{ $this->account->hasVerifiedEmail() ? 'text-teal-700' : 'text-amber-700' }}">{{ $this->account->hasVerifiedEmail() ? 'Verificado' : 'Pendiente' }}</dd></div>
        <div><dt class="text-gray-500">Cuenta creada</dt><dd class="mt-0.5 text-gray-900">{{ $this->account->created_at?->format('d/m/Y') ?? 'Sin fecha registrada' }}</dd></div>
    </dl>
</section>
