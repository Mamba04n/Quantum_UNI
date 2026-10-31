<?php

use App\Domain\Clientes\Actions\RegisterPortalCustomer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-simple')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $errorMessage = '';

    public string $website = '';

    public function mount(): void
    {
        if (request()->query('continue') === 'quote') {
            session()->put('url.intended', route('customer.quote'));
        }
    }

    public function register(): void
    {
        $this->errorMessage = '';

        $normalizedEmail = Str::lower(trim($this->email));
        $throttleKeys = [
            'public-registration:ip:'.request()->ip(),
            'public-registration:email:'.hash('sha256', $normalizedEmail),
        ];
        $blockedKey = collect($throttleKeys)->first(
            fn (string $key): bool => RateLimiter::tooManyAttempts($key, 5)
        );

        if ($blockedKey !== null) {
            $seconds = RateLimiter::availableIn($blockedKey);
            $this->errorMessage = "Demasiados intentos. Intenta nuevamente en {$seconds} segundos.";

            return;
        }

        foreach ($throttleKeys as $throttleKey) {
            RateLimiter::hit($throttleKey, 600);
        }

        // Campo honeypot: los navegadores normales no lo completan.
        if ($this->website !== '') {
            $this->errorMessage = 'No fue posible completar el registro. Intenta nuevamente.';

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'not_regex:/[\r\n]/', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $user = app(RegisterPortalCustomer::class)->execute([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'phone_mobile' => $this->phone,
                'address' => $this->address,
                'exact_address' => $this->address,
                'password' => $this->password,
            ]);

            Auth::login($user);

            $this->redirect(route('verification.notice', absolute: false), navigate: true);

        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        } catch (Throwable $exception) {
            $incidentId = (string) Str::ulid();
            Log::error('Fallo en registro público.', [
                'incident_id' => $incidentId,
                'exception' => $exception,
            ]);
            $this->errorMessage = "No fue posible completar el registro. Incidente: {$incidentId}.";
        }
    }
}; ?>

@php
    $logoFullVersion = filemtime(public_path('storefront/images/logo-completo.png'));
@endphp

<div class="login-shell">
    <style>
        .register-fields { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px; }
        .register-fields .form-row,.register-fields input { min-width:0;max-width:100%; }
        @media(max-width:600px){ .register-fields { grid-template-columns:minmax(0,1fr);gap:0; } }
        /* Estilos específicos para compactar el registro */
        .register-card h2 { font-size: 1.35rem !important; margin-bottom: 8px !important; }
        .register-card .login-card-sub { font-size: 0.85rem !important; margin-bottom: 12px !important; }
        .register-card .form-row { margin-bottom: 8px !important; gap: 4px !important; }
        .register-card .form-row label { font-size: 0.8rem !important; font-weight: 600; color: #444; }
        .register-card .form-row input { height: 38px !important; font-size: 0.9rem !important; }
        .register-card .form-row textarea { font-size: 0.85rem !important; padding: 8px !important; min-height: 60px !important; }
        .register-card .btn { min-height: 38px !important; font-size: 0.9rem !important; }
        @media (min-width: 980px) {
            .register-wrap { padding: 20px 46px !important; }
        }
    </style>

    <div class="login-layout">
        <section class="login-brand-panel" aria-label="Identidad de marca">
            <img class="login-brand-logo" src="{{ asset('storefront/images/logo-completo.png') }}?v={{ $logoFullVersion }}" alt="Quantum Soluciones" loading="eager" decoding="async">
        </section>

        <div class="login-wrap register-wrap">
            <form class="login-card register-card" wire:submit.prevent="register" style="max-width: 480px;">
                <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
                    <label for="website">Sitio web</label>
                    <input id="website" wire:model="website" type="text" tabindex="-1" autocomplete="off">
                </div>
                @if($errorMessage)
                    <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 15px; border: 1px solid #fecaca;">
                        {{ $errorMessage }}
                    </div>
                @endif

                <h2>Crear cuenta de cliente</h2>
                <p class="login-card-sub">Completa tus datos para empezar a gestionar tus pedidos.</p>

                <div class="form-row">
                    <label>Nombre Completo / Razón Social</label>
                    <input wire:model="name" type="text" autofocus autocomplete="name" placeholder="Ej: Juan Pérez">
                    @error('name') <p class="text-red-500" style="font-size: 10px; margin-top: 2px;">{{ $message }}</p> @enderror
                </div>

                <div class="register-fields">
                    <div class="form-row">
                        <label>Correo Electrónico</label>
                        <input wire:model="email" type="email" autocomplete="username" placeholder="correo@ejemplo.com">
                        @error('email') <p class="text-red-500" style="font-size: 10px; margin-top: 2px;">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-row">
                        <label>Teléfono / WhatsApp</label>
                        <input wire:model="phone" type="text" autocomplete="tel" placeholder="+505 0000 0000">
                        @error('phone') <p class="text-red-500" style="font-size: 10px; margin-top: 2px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <label>Dirección Completa de Entrega</label>
                    <textarea wire:model="address" placeholder="Ciudad, Barrio/Calle y puntos de referencia..."></textarea>
                    @error('address') <p class="text-red-500" style="font-size: 10px; margin-top: 2px;">{{ $message }}</p> @enderror
                </div>

                <div class="register-fields">
                    <div class="form-row">
                        <label>Contraseña</label>
                        <input wire:model="password" type="password" autocomplete="new-password">
                        @error('password') <p class="text-red-500" style="font-size: 10px; margin-top: 2px;">{{ $message }}</p> @enderror
                    </div>
                    <div class="form-row">
                        <label>Confirmar</label>
                        <input wire:model="password_confirmation" type="password" autocomplete="new-password">
                    </div>
                </div>

                <button class="btn" type="submit" style="margin-top: 8px;" wire:loading.attr="disabled">
                    <span wire:loading.remove>Registrar mi cuenta</span>
                    <span wire:loading>Procesando...</span>
                </button>

                <div style="margin-top:15px; text-align:center;">
                    <a href="{{ route('login') }}" style="font-size:11px; color:#666; text-decoration:underline; font-weight:600;">¿Ya tienes cuenta? Iniciar sesión</a>
                </div>
            </form>
        </div>
    </div>
</div>
