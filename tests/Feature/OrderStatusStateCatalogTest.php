<?php

namespace Tests\Feature;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use Tests\TestCase;

class OrderStatusStateCatalogTest extends TestCase
{
    public function test_sent_customer_item_moves_to_delivery_confirmation_phase(): void
    {
        $phase = app(OrderStatusStateCatalog::class)->phaseForItemStatus(
            OrderStatusService::STATUS_SENT_CUSTOMER
        );

        $this->assertSame(OrderStatusService::PHASE_DELIVERY, $phase);
    }

    public function test_dispatch_authorization_request_stays_in_kardex_output_phase(): void
    {
        $catalog = app(OrderStatusStateCatalog::class);

        $this->assertSame(
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $catalog->phaseForItemStatus(OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED)
        );
        $this->assertSame(
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $catalog->phaseForGeneralStatus('autorizacion_salida_pendiente')
        );
        $this->assertSame(
            'Autorización de salida solicitada',
            $catalog->humanStatus(OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED)
        );
    }

    public function test_received_warehouse_items_move_to_facturacion_phase(): void
    {
        $catalog = app(OrderStatusStateCatalog::class);

        $this->assertSame(
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $catalog->phaseForItemStatus(OrderStatusService::STATUS_RECEIVED_WAREHOUSE)
        );
        $this->assertSame(
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $catalog->phaseForItemStatus(OrderStatusService::STATUS_KARDEX_ENTRY)
        );
        $this->assertSame(
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $catalog->phaseForItemStatus(OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH)
        );
        $this->assertSame(
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $catalog->phaseForGeneralStatus('recibido_total')
        );
        $this->assertSame(
            OrderStatusService::PHASE_WAREHOUSE,
            $catalog->phaseForItemStatus(OrderStatusService::STATUS_IN_CUSTOMS)
        );
    }
}
