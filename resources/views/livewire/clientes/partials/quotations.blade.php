<div class="cp-page-heading"><div><p class="cp-eyebrow">Solicitudes y ofertas</p><h1>Mis cotizaciones</h1><p>Consulta los repuestos solicitados y el estado de cada solicitud.</p></div><a href="{{ route('storefront.products.index') }}" class="cp-button cp-button--primary"><x-portal.icon name="plus" />Nueva cotización</a></div>
<div class="cp-section-note"><x-portal.icon name="quote" /><span><strong>{{ $quotations->count() }}</strong> cotizaciones registradas</span></div>
<div class="cp-stack">
    @forelse($quotations as $quote)
        <article class="cp-panel cp-quotation" wire:key="portal-quote-{{ $quote->id }}"><div class="cp-panel-heading"><div class="cp-title-icon"><span class="cp-record-icon"><x-portal.icon name="quote" /></span><div><h2>{{ $quote->reference }}</h2><p>{{ $quote->created_at?->format('d/m/Y · H:i') ?: $quote->date }}</p></div></div>@include('livewire.clientes.partials.quote-status')</div>
            <div class="cp-quote-meta"><span><x-portal.icon name="machine" />{{ $quote->machinery ?: 'Equipo por especificar' }} {{ $quote->machine_brand }}</span><span>Urgencia: <strong>{{ ucfirst($quote->request_urgency ?: 'Media') }}</strong></span></div>
            <div class="cp-table-wrap"><table class="cp-table"><caption class="cp-sr-only">Partidas de {{ $quote->reference }}</caption><thead><tr><th scope="col">Repuesto solicitado</th><th scope="col">N.º de parte</th><th scope="col" class="cp-align-right">Cantidad</th></tr></thead><tbody>@forelse($quote->items as $item)<tr><td>{{ $item->part_name ?: $item->description }}</td><td>{{ $item->part_number ?: 'Por confirmar' }}</td><td class="cp-align-right">{{ $item->quantity }} {{ $item->unit ?: 'PZA' }}</td></tr>@empty<tr><td colspan="3">No hay partidas registradas.</td></tr>@endforelse</tbody></table></div>
            @if($quote->notes)<p class="cp-note"><strong>Notas de tu solicitud:</strong> {{ $quote->notes }}</p>@endif
        </article>
    @empty
        <section class="cp-panel"><x-portal.empty icon="quote" title="Aún no tienes cotizaciones registradas" description="Agrega los repuestos que necesitas y solicita una cotización. Podrás seguirla desde este espacio."><a href="{{ route('storefront.products.index') }}" class="cp-button cp-button--primary">Explorar repuestos <x-portal.icon name="arrow" /></a></x-portal.empty></section>
    @endforelse
</div>
