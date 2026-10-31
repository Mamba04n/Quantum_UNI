@php
    $displayName = $entity->name ?? $user->name;
    $firstName = str($user->name)->before(' ');
    $initials = mb_strtoupper(mb_substr(trim($user->name), 0, 2, 'UTF-8'), 'UTF-8');
    $openTickets = $tickets->whereNotIn('status', ['resuelto', 'cerrado'])->count();
    $navItems = [
        'dashboard' => ['label' => 'Inicio', 'icon' => 'home'],
        'quotations' => ['label' => 'Mis cotizaciones', 'icon' => 'quote'],
        'orders' => ['label' => 'Mis pedidos', 'icon' => 'box'],
        'order_detail' => ['label' => 'Seguimiento', 'icon' => 'pin'],
        'profile' => ['label' => 'Mi cuenta', 'icon' => 'user'],
        'tickets' => ['label' => 'Soporte', 'icon' => 'support'],
    ];
@endphp
<div class="cp-shell" x-data x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' })">
    <a class="cp-skip" href="#portal-content">Saltar al contenido</a>
    <aside class="cp-sidebar" aria-label="Navegación del portal">@include('livewire.clientes.partials.navigation', ['mobile' => false])</aside>
    <dialog class="cp-mobile-menu" x-ref="navigation" aria-label="Menú del portal" x-on:click="if ($event.target === $el) $el.close()">
        <div class="cp-menu-close"><span>Mi portal</span><button type="button" class="cp-icon-button" x-on:click="$refs.navigation.close()" aria-label="Cerrar menú"><x-portal.icon name="close" /></button></div>
        @include('livewire.clientes.partials.navigation', ['mobile' => true])
    </dialog>
    <div class="cp-workspace">
        <header class="cp-topbar">
            <div class="cp-location"><button type="button" class="cp-icon-button cp-menu-button" x-on:click="$refs.navigation.showModal()" aria-label="Abrir menú del portal"><x-portal.icon name="menu" /></button><span>Mi portal <span class="cp-location-divider">/</span> <strong>{{ $navItems[$view]['label'] ?? 'Inicio' }}</strong></span></div>
            <form wire:submit="searchTrackingCode" class="cp-search" role="search"><label class="cp-sr-only" for="portal-tracking">Buscar pedido o código de seguimiento</label><x-portal.icon name="search" /><input id="portal-tracking" wire:model="trackingCode" placeholder="Pedido o código de seguimiento" autocomplete="off" aria-describedby="portal-tracking-error"><button type="submit" wire:loading.attr="disabled" wire:target="searchTrackingCode" aria-label="Buscar pedido"><x-portal.icon name="arrow" /></button></form>
            <button type="button" wire:click="setView('profile')" class="cp-top-account" aria-label="Ver mi cuenta"><span class="cp-avatar cp-avatar--small">{{ $initials }}</span><span>{{ $firstName }}</span></button>
        </header>
        <main id="portal-content" class="cp-content" tabindex="-1">
            @if($trackingError)<div class="cp-alert" id="portal-tracking-error" role="alert"><x-portal.icon name="search" /><span>{{ $trackingError }}</span></div>@else<span id="portal-tracking-error"></span>@endif
            @if($view === 'dashboard')
                @include('livewire.clientes.partials.dashboard')
            @elseif($view === 'quotations')
                @include('livewire.clientes.partials.quotations')
            @elseif($view === 'orders')
                <div class="cp-page-heading"><div><p class="cp-eyebrow">Tus compras</p><h1>Mis pedidos</h1><p>Revisa tus pedidos en curso y el historial de entregas.</p></div><a href="{{ route('storefront.products.index') }}" class="cp-button"><x-portal.icon name="plus" />Cotizar repuestos</a></div>
                <div class="cp-order-summary"><span><x-portal.icon name="box" /><strong>{{ $activeOrders->count() }}</strong> en curso</span><span><x-portal.icon name="check" /><strong>{{ $completedOrders->count() }}</strong> en el historial</span><span>Promedio por pedido <strong>C$ {{ number_format((float)$stats['average_amount'], 2) }}</strong></span></div>
                <div class="cp-stack"><section class="cp-panel"><div class="cp-panel-heading"><div class="cp-title-icon"><x-portal.icon name="truck" /><h2>En curso</h2><span class="cp-count">{{ $activeOrders->count() }}</span></div></div>@include('livewire.clientes.partials.order-list', ['orders' => $activeOrders])</section><section class="cp-panel"><div class="cp-panel-heading"><div class="cp-title-icon"><x-portal.icon name="clock" /><h2>Historial</h2><span class="cp-count">{{ $completedOrders->count() }}</span></div></div>@include('livewire.clientes.partials.order-list', ['orders' => $completedOrders])</section></div>
            @elseif($view === 'order_detail')
                @include('livewire.clientes.partials.tracking')
            @elseif($view === 'profile')
                @include('livewire.clientes.partials.profile')
            @elseif($view === 'tickets')
                <div class="cp-page-heading"><div><p class="cp-eyebrow">Estamos para ayudarte</p><h1>Centro de soporte</h1><p>Consulta tus conversaciones o crea una solicitud para nuestro equipo.</p></div></div>
                <livewire:clientes.ticket-center :embedded="true" />
            @endif
            <footer class="cp-footer"><span>Quantum Soluciones <span aria-hidden="true">·</span> Portal de clientes</span><a href="{{ route('storefront.home') }}">Volver al sitio web <x-portal.icon name="arrow" /></a></footer>
        </main>
    </div>
</div>
