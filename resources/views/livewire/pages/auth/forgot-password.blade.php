<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-simple')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'not_regex:/[\r\n]/', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');
        session()->flash('status', __($status));
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
                <h2>Recuperar contraseña</h2>
                <p class="login-card-sub">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>

                @if(session('status'))
                    <div style="margin-bottom: 15px; padding: 10px; background: #f0fdf4; color: #166534; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid #bbf7d0;">
                        {{ session('status') }}
                    </div>
                @endif

                <form wire:submit="sendPasswordResetLink">
                    <div class="form-row">
                        <label>Correo Electrónico</label>
                        <input wire:model="email" type="email" required autofocus placeholder="correo@ejemplo.com">
                        @error('email') <p class="text-red-500" style="font-size: 11px; margin-top: 5px;">{{ $message }}</p> @enderror
                    </div>

                    <button class="btn" type="submit" style="margin-top: 10px;">Enviar enlace de recuperación</button>

                    <div style="margin-top:20px; text-align:center;">
                        <a href="{{ route('login') }}" style="font-size:12px; color:#666; text-decoration:underline; font-weight:600;">Regresar al inicio de sesión</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
