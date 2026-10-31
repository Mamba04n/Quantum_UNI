<?php

use App\Livewire\Actions\Logout;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.verify-email')] class extends Component
{
    /**
     * Envia una notificacion de verificacion al usuario.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: RouteServiceProvider::HOME, navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Cierra la sesion del usuario actual.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="verification-card" aria-labelledby="verification-title">
    <ol class="verification-steps" aria-label="Registro de cuenta"><li><span aria-hidden="true">✓</span>Cuenta creada</li><li aria-current="step"><span>2</span>Verificar correo</li></ol>
    <div class="verification-mail-icon"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="7" width="24" height="18" rx="3"/><path d="m5 9 11 9L27 9"/></svg></div>
    <h1 id="verification-title">Confirma tu correo electrónico</h1>
    <p class="verification-intro">Tu cuenta ya está creada. Abre el enlace de verificación que enviamos a:</p>
    <div class="verification-address">{{ auth()->user()->email }}</div>
    <p class="verification-description">Este paso confirma que el correo te pertenece y te permite continuar con tu cuenta Quantum.</p>
    @if(session('status') === 'verification-link-sent')
        <div class="verification-success" role="status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg><span><strong>Nuevo enlace enviado</strong>Revisa tu bandeja de entrada y abre el correo de Quantum Soluciones.</span></div>
    @endif
    <div class="verification-actions"><button type="button" wire:click="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification,logout" class="verification-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M5 8a8 8 0 0 1 13-2l2 2M4 16l2 2a8 8 0 0 0 13-2"/></svg><span wire:loading.remove wire:target="sendVerification">Reenviar enlace de verificación</span><span wire:loading wire:target="sendVerification">Enviando enlace…</span></button></div>
    <div class="verification-help"><strong>¿No encuentras el correo?</strong><p>Revisa la carpeta de spam o correo no deseado. Si aún no aparece, puedes solicitar otro enlace con el botón de arriba.</p></div>
    <div class="verification-session"><span>¿Prefieres usar otra cuenta?</span><button type="button" wire:click="logout" wire:loading.attr="disabled" wire:target="sendVerification,logout"><span wire:loading.remove wire:target="logout">Cerrar sesión</span><span wire:loading wire:target="logout">Cerrando sesión…</span></button></div>
</section>
