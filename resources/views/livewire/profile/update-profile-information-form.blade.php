<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Monta el componente.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Actualiza la informacion de perfil del usuario autenticado.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'not_regex:/[\r\n]/', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Envia una notificacion de verificacion al usuario actual.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: RouteServiceProvider::HOME);

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="space-y-4">
    <header>
        <div>
        <h2 class="text-base font-semibold text-gray-900">
            Información personal
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Actualiza el nombre y correo asociados a tu cuenta.
        </p>
        </div>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-4">
        <div>
            <x-input-label for="name" value="Nombre" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="mt-3 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                        Correo pendiente de verificación.

                        <button type="button" wire:click.prevent="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification" class="rounded-md font-semibold underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700 disabled:opacity-50">
                            Reenviar enlace
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            Se envio un nuevo enlace de verificacion a tu correo.
                        </p>
                    @endif
                </div>
            @elseif(auth()->user()->hasVerifiedEmail())
                <p class="mt-3 inline-flex items-center gap-2 text-sm text-teal-700"><x-workspace-icon name="check" class="h-4 w-4"/> Correo verificado</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="updateProfileInformation" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-50">
                <span wire:loading.remove wire:target="updateProfileInformation">Guardar datos</span><span wire:loading wire:target="updateProfileInformation">Guardando...</span>
            </button>

            <x-action-message class="me-3" on="profile-updated">
                Guardado.
            </x-action-message>
        </div>
    </form>
</section>
