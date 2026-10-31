<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.quantum-auth')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $user = Auth::user();
        if ($user instanceof User && $user->hasRole(User::ROLE_CLIENTE_TIENDA)) {
            $this->redirect(route('customer.portal'), navigate: false);

            return;
        }

        $this->redirectIntended(default: RouteServiceProvider::HOME, navigate: true);
    }
}; ?>

<div>
    <form class="login-card" wire:submit="login">
        <h2>Iniciar sesión</h2>
        <p class="login-card-sub">Empleados: panel ERP. Clientes registrados: vuelves a la tienda tras entrar.</p>

        @if (session('status'))
            <p class="error">{{ session('status') }}</p>
        @endif

        @error('form.email')<p class="error">{{ $message }}</p>@enderror
        @error('form.password')<p class="error">{{ $message }}</p>@enderror

        <div class="form-row">
            <label>Correo</label>
            <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username">
        </div>

        <div class="form-row">
            <label>Contraseña</label>
            <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password">
        </div>

        <button class="btn" type="submit">Entrar</button>
    </form>
</div>
