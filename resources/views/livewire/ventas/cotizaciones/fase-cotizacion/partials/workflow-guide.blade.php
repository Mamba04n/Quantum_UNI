@php
    $stageGuidance = [
        'customer_request' => [
            'title' => 'Revisa la solicitud antes de cotizar',
            'description' => 'Confirma cliente, productos, referencias y cantidades. Después envía la solicitud a licitación.',
            'steps' => ['Revisar datos del cliente', 'Confirmar productos y cantidades', 'Avanzar a licitación'],
        ],
        'supplier_bid' => [
            'title' => 'Prepara y envía los RFQ',
            'description' => 'Selecciona los productos de cada proveedor, genera el PDF y márcalo como enviado.',
            'steps' => ['Confirmar productos', 'Generar RFQ', 'Marcar RFQ enviado'],
        ],
        'supplier_response' => [
            'title' => 'Registra las respuestas recibidas',
            'description' => 'Captura la respuesta de cada proveedor o marca que no respondió antes de continuar a la oferta.',
            'steps' => ['Revisar RFQ enviados', 'Registrar respuestas', 'Avanzar a Oferta Cliente'],
        ],
        'client_offer' => [
            'title' => 'Prepara la oferta para el cliente',
            'description' => 'Define los precios finales de venta al cliente, genera el PDF y envíala.',
            'steps' => ['Definir precios', 'Generar PDF de Oferta', 'Enviar al cliente'],
        ],
        'order_changes' => [
            'title' => 'Corrige los cambios solicitados',
            'description' => 'Aplica únicamente las correcciones pedidas y vuelve a generar la oferta.',
            'steps' => ['Leer observaciones', 'Corregir la orden', 'Regenerar oferta'],
        ],
        'customer_approval' => [
            'title' => 'Registra la decisión del cliente',
            'description' => 'Registra si el cliente aceptó o rechazó la cotización.',
            'steps' => ['Revisar respuesta', 'Registrar decisión', 'Avanzar a Compra'],
        ],
        'purchase_preparation' => [
            'title' => 'Prepara las órdenes de compra',
            'description' => 'Confirma el proveedor final a comprar y emite las Órdenes de Compra.',
            'steps' => ['Confirmar proveedor final', 'Generar OC'],
        ],
    ];
    $guide = $stageGuidance[$currentStage] ?? [
        'title' => 'Continúa con la etapa actual',
        'description' => $this->nextActionForStatus($selectedQuote->status),
        'steps' => ['Revisar información', 'Completar datos', 'Continuar'],
    ];
@endphp

<section class="p-4" aria-label="Guía de la etapa">
    <div>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="text-sm font-medium text-slate-500">Siguiente acción</span>
                <h3 class="text-sm font-semibold text-slate-900">{{ $guide['title'] }}</h3>
            </div>
            <p class="mt-1 text-sm text-slate-600">{{ $guide['description'] }}</p>
            <ol class="mt-3 space-y-2" aria-label="Pasos recomendados">
                @foreach($guide['steps'] as $stepIndex => $step)
                    <li class="flex items-start gap-2 text-sm text-slate-600">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-white text-[10px] font-black text-slate-700 ring-1 ring-slate-200">{{ $stepIndex + 1 }}</span>
                        <span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

    </div>
</section>
