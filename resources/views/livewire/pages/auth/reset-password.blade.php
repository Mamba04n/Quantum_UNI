<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-simple')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'not_regex:/[\r\n]/', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

@php
    $logoFullVersion = filemtime(public_path('storefront/images/logo-completo.png'));
@endphp

<div class="login-shell">
    <div class="login-layout">
        <section class="login-brand-panel" aria-label="Identidad de marca">
            <img class="login-brand-logo" src="{{ asset('storefront/images/logo-completo.png') }}?v={{ $logoFullVersion }}" alt="Quantum Soluciones" loading="eager" decoding="async">
        </section>

        <div class="login-wrap">
            <div class="login-card">
                <h2>Nueva contraseña</h2>
                <p class="login-card-sub">Crea una nueva contraseña segura para tu cuenta.</p>

                <form wire:submit="resetPassword">
                    <div class="form-row">
                        <label>Correo Electrónico</label>
                        <input wire:model="email" type="email" required readonly placeholder="correo@ejemplo.com">
                        @error('email') <p class="text-red-500" style="font-size: 11px; margin-top: 5px;">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-row">
                        <label>Nueva Contraseña</label>
                        <input wire:model="password" type="password" required autofocus>
                        @error('password') <p class="text-red-500" style="font-size: 11px; margin-top: 5px;">{{ $message }}</p> @enderror
                    </div>

                    <div class="form-row">
                        <label>Confirmar Contraseña</label>
                        <input wire:model="password_confirmation" type="password" required>
                    </div>

                    <button class="btn" type="submit" style="margin-top: 10px;">Restablecer contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
