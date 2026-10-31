@extends('public-commerce.layout')
@section('title','Pago')
@section('content')
<div class="page-heading"><p class="eyebrow">{{ $link->code }}</p><h1>Pago de tu pedido</h1><p class="muted">{{ $link->description }}</p></div>
<div class="checkout-grid">
    <section class="payment-column" aria-label="Opciones de pago">
        @if($link->status==='paid')
            <div class="confirmation"><span class="status-mark" aria-hidden="true">✓</span><h2>Pago recibido</h2><p class="muted">Tu pago de USD {{ $link->total }} está confirmado.</p></div>
            @if($tracking)
                <div class="tracking-access"><p class="muted">Sigue tu pedido</p><strong class="tracking-code">{{ $tracking }}</strong><a class="button" href="{{ route('public.tracking',$tracking) }}">Ver seguimiento <span aria-hidden="true">→</span></a></div>
            @else<p>Tu seguimiento estará disponible cuando el pedido entre en preparación.</p>@endif
        @elseif($link->effectiveStatus()==='expired')
            <h2>Enlace vencido</h2><p role="status">Este enlace de pago ha vencido. Contacta con Quantum para solicitar uno nuevo.</p>
        @elseif($link->effectiveStatus()!=='pending')
            <h2>Enlace no disponible</h2><p role="status">Este enlace ya no está disponible.</p>
        @elseif(config('services.paypal.environment')==='sandbox' && config('services.paypal.client_id') && config('services.paypal.client_secret'))
            <h2>Elige cómo pagar</h2><p class="muted">El pago se completa en el checkout seguro de PayPal.</p>
            <div id="paypal-checkout" data-client-id="{{ config('services.paypal.client_id') }}" data-amount="{{ $link->total }}" data-order-url="{{ route('public.payment.order',$link->public_token) }}" data-capture-url="{{ route('public.payment.capture',$link->public_token) }}">
                <div id="paypal-method" class="payment-method" hidden><div class="method-heading"><strong>PayPal</strong><span class="method-symbol" aria-hidden="true">P</span></div><p class="muted">Paga con tu cuenta PayPal.</p><paypal-button id="paypal-button" hidden></paypal-button></div>
                <div id="card-method" class="payment-method" hidden><div class="method-heading"><strong>Tarjeta de débito o crédito</strong><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M6 15h4"/></svg></div><p class="muted">Completa los datos de tu tarjeta directamente en PayPal.</p><paypal-basic-card-container id="guest-card-container" hidden><paypal-basic-card-button id="guest-card-button"></paypal-basic-card-button></paypal-basic-card-container></div>
            </div>
            <p id="payment-feedback" class="payment-feedback" role="status">Cargando opciones de PayPal…</p>
            <div class="payment-note"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg><p>No necesitas una cuenta Quantum. Las opciones disponibles las determina PayPal.</p></div>
        @else<h2>Pago en línea</h2><p role="status">El pago en línea aún no está disponible. Contacta con Quantum Soluciones.</p>@endif
        <a class="support-link" href="{{ url('/contacto') }}">¿Necesitas ayuda con tu pago? →</a>
    </section>
    <aside class="order-summary" aria-label="Resumen del pedido">
        <h2>Resumen del pedido</h2><p class="muted">{{ $link->items->count() }} {{ $link->items->count()===1?'concepto':'conceptos' }}</p>
        <ul class="summary-items">@foreach($link->items as $i)<li><img class="summary-image" src="{{ $i->image_url }}" alt="{{ $i->description }}" loading="lazy"/><div class="summary-description"><span class="item-quantity">{{ $i->quantity }} ×</span><strong>{{ $i->description }}</strong>@if($i->part_number)<small>Parte {{ $i->part_number }}</small>@endif</div><span class="amount">USD {{ $i->line_total }}</span></li>@endforeach</ul>
        <div class="summary-subtotal"><span>Subtotal</span><span>USD {{ $link->total }}</span></div><div class="total"><span>Total</span><strong>USD {{ $link->total }}</strong></div>
        @if($link->effectiveStatus()==='pending')<p class="summary-validity">Disponible hasta {{ $link->expires_at->format('d/m/Y H:i') }}</p>@endif
    </aside>
</div>
@endsection
@section('scripts')
@if($link->effectiveStatus()==='pending' && config('services.paypal.environment')==='sandbox' && config('services.paypal.client_id') && config('services.paypal.client_secret'))
<script src="https://www.sandbox.paypal.com/web-sdk/v6/core"></script><script src="{{ asset('js/public-paypal.js') }}?v={{ filemtime(public_path('js/public-paypal.js')) }}" defer></script>
@endif
@endsection
