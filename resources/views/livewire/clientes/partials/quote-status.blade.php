<span class="cp-status">
    @if(in_array($quote->status, ['recibida', 'en_revision', 'lista_para_licitar', 'pendiente_licitacion']))
        Licitación con proveedores
    @elseif(in_array($quote->status, ['oferta_generada', 'oferta_enviada_cliente', 'sent_to_customer']))
        Oferta disponible
    @elseif(in_array($quote->status, ['aprobada_total', 'aprobada_cliente_total', 'customer_accepted_total']))
        Aprobada
    @else
        {{ match($quote->status) { 'draft' => 'Borrador', 'supplier_response_pending' => 'Esperando respuesta del proveedor', default => ucfirst(str_replace('_', ' ', $quote->status)) } }}
    @endif
</span>
