<div class="cp-page-heading"><div><p class="cp-eyebrow">Portal de clientes</p><h1>Hola, {{ $firstName }}.</h1><p>Tus repuestos, pedidos y soporte, en un solo lugar.</p></div><a class="cp-button cp-button--primary" href="{{ route('storefront.products.index') }}"><x-portal.icon name="plus" />Cotizar repuestos</a></div>
<section class="cp-metrics" aria-label="Resumen de mi cuenta">
    @foreach([
        ['Cotizaciones', $quotations->count(), 'Solicitudes registradas', 'quote', 'quotations'],
        ['Pedidos activos', $stats['active'], 'En gestión actualmente', 'box', 'orders'],
        ['En tránsito', $stats['in_transit'], 'Proveedor, aduana o ruta', 'truck', 'orders'],
        ['Entregados', $stats['delivered'], 'Pedidos completados', 'check', 'orders'],
    ] as [$label, $value, $caption, $icon, $target])
        <button type="button" wire:click="setView('{{ $target }}')" class="cp-metric"><span class="cp-metric-top"><span>{{ $label }}</span><x-portal.icon :name="$icon" /></span><strong>{{ $value }}</strong><span class="cp-caption">{{ $caption }}</span></button>
    @endforeach
</section>
<div class="cp-dashboard-grid">
    <div class="cp-stack">
        <section class="cp-panel"><div class="cp-panel-heading"><div class="cp-title-icon"><x-portal.icon name="quote" /><h2>Cotizaciones recientes</h2></div><button type="button" wire:click="setView('quotations')" class="cp-text-link">Ver todas <x-portal.icon name="arrow" /></button></div>
            @forelse($quotations->take(3) as $quote)
                <button type="button" wire:click="setView('quotations')" class="cp-record-row"><span class="cp-record-icon"><x-portal.icon name="quote" /></span><span class="cp-record-copy"><strong>{{ $quote->reference }}</strong><span>{{ $quote->items->count() }} partidas · {{ $quote->created_at?->format('d/m/Y') }}</span></span>@include('livewire.clientes.partials.quote-status')</button>
            @empty
                <x-portal.empty icon="quote" title="Tu próxima cotización empieza aquí" description="Busca tus repuestos y envíanos una solicitud. Aquí podrás consultar su estado."><a href="{{ route('storefront.products.index') }}" class="cp-text-link">Explorar repuestos <x-portal.icon name="arrow" /></a></x-portal.empty>
            @endforelse
        </section>
        <section class="cp-panel"><div class="cp-panel-heading"><div class="cp-title-icon"><x-portal.icon name="box" /><h2>Pedidos recientes</h2></div><button type="button" wire:click="setView('orders')" class="cp-text-link">Ver todos <x-portal.icon name="arrow" /></button></div>@include('livewire.clientes.partials.order-list', ['orders' => $orders->take(3)])</section>
    </div>
    <aside class="cp-stack">
        <section class="cp-assistance"><span class="cp-assistance-icon"><x-portal.icon name="support" /></span><p class="cp-eyebrow">Atención Quantum</p><h2>¿Necesitas una mano con tu equipo?</h2><p>Comparte el modelo o una foto de la pieza. Nuestro equipo te orienta.</p><button type="button" wire:click="setView('tickets')" class="cp-button cp-button--light">Ir a soporte <x-portal.icon name="arrow" /></button></section>
        <section class="cp-panel cp-profile-summary"><div class="cp-title-icon"><x-portal.icon name="user" /><h2>Tu información</h2><strong>{{ $profileCompletion }}%</strong></div><progress max="100" value="{{ $profileCompletion }}" aria-label="Perfil completado">{{ $profileCompletion }}%</progress><p>Mantén a mano tus datos de contacto para tus solicitudes.</p><button type="button" wire:click="setView('profile')" class="cp-text-link">Consultar mi cuenta <x-portal.icon name="arrow" /></button></section>
    </aside>
</div>
<nav class="cp-resource-links" aria-label="Recursos para tus solicitudes"><a href="{{ route('storefront.products.index') }}"><x-portal.icon name="box" /><span><strong>Encuentra tu repuesto</strong><small>Busca por marca o número de parte</small></span><x-portal.icon name="arrow" /></a><a href="{{ route('storefront.catalogs.index') }}"><x-portal.icon name="book" /><span><strong>Catálogos técnicos</strong><small>Consulta referencias y documentación</small></span><x-portal.icon name="arrow" /></a></nav>
