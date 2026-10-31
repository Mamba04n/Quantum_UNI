@php
    $bannerNumber = preg_replace('/\D+/', '', config('services.storefront.whatsapp_owner') ?: '50583262783');
    $whatsappUrl = 'https://wa.me/'.$bannerNumber.'?text='.rawurlencode('¡Hola, Quantum! Quisiera cotizar una pieza.');
    $whatsappDisplay = preg_replace('/^(505)(\d{4})(\d{4})$/', '+$1 $2 $3', $bannerNumber);
@endphp
<aside class="quantum-whatsapp-banner container" aria-label="Cotizaciones por WhatsApp"><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><span class="quantum-whatsapp-banner-copy"><small>Repuestos y soporte técnico</small><strong>Lo que tu maquinaria necesita,<br>pídelo por WhatsApp.</strong></span><span class="quantum-whatsapp-banner-contact"><img src="{{ asset('storefront/images/whatsapp-glyph-official.svg') }}" alt="" width="32" height="32"><span><b>Cotiza y compra</b><small>{{ $whatsappDisplay }}</small></span><span aria-hidden="true">&rarr;</span></span></a></aside>
