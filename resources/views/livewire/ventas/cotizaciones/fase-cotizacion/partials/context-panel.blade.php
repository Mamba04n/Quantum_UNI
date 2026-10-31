<aside aria-label="Contexto de la cotización" class="min-w-0">
    <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-900">Resumen de la solicitud</h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-gray-500">Cliente</dt><dd class="mt-0.5 break-words font-medium text-gray-900">{{ $selectedQuote->quote_to_name ?: 'Sin cliente' }}</dd></div>
            @if($selectedQuote->customer_contact_name)
                <div><dt class="text-gray-500">Contacto</dt><dd class="mt-0.5 break-words text-gray-900">{{ $selectedQuote->customer_contact_name }}</dd></div>
            @endif
            <div><dt class="text-gray-500">Equipo</dt><dd class="mt-0.5 break-words text-gray-900">{{ $selectedQuote->machinery ?: 'Sin especificar' }}</dd></div>
            @if($selectedQuote->machine_brand || $selectedQuote->machine_model)
                <div><dt class="text-gray-500">Marca / modelo</dt><dd class="mt-0.5 break-words text-gray-900">{{ collect([$selectedQuote->machine_brand, $selectedQuote->machine_model])->filter()->join(' · ') }}</dd></div>
            @endif
            <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                <div><dt class="text-gray-500">Partidas</dt><dd class="mt-0.5 font-semibold text-gray-900">{{ $selectedQuote->items->count() }}</dd></div>
                <div><dt class="text-gray-500">Urgencia</dt><dd class="mt-0.5 font-semibold {{ in_array($selectedQuote->request_urgency, ['alta', 'urgente']) ? 'text-amber-800' : 'text-gray-900' }}">{{ ucfirst($selectedQuote->request_urgency ?: 'Sin definir') }}</dd></div>
            </div>
            <div><dt class="text-gray-500">Fecha de solicitud</dt><dd class="mt-0.5 text-gray-900">{{ $selectedQuote->date?->format('d/m/Y') ?: 'Sin fecha' }}</dd></div>
        </dl>
    </section>

</aside>
