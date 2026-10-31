<?php

namespace App\Domain\Pedidos\OrderStatus\Support;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use Illuminate\Support\Collection;

class OrderStatusGeneralStatusResolver
{
    public function resolve(Collection $statuses): string
    {
        $warehouseReadyStatuses = [
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL,
            OrderStatusService::STATUS_KARDEX_ENTRY,
            OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL,
            OrderStatusService::STATUS_RESERVED_CUSTOMER,
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
            OrderStatusService::STATUS_HELD_BY_ADMIN,
            OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED,
            OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH,
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS,
            OrderStatusService::STATUS_KARDEX_OUTPUT,
            OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL,
            OrderStatusService::STATUS_SENT_CUSTOMER,
            OrderStatusService::STATUS_DELIVERED,
            OrderStatusService::STATUS_CLOSED,
            OrderStatusService::STATUS_CANCELLED_SUPPLIER,
        ];

        return match (true) {
            $statuses->every(fn ($s) => $s === OrderStatusService::STATUS_CANCELLED_SUPPLIER) => 'cancelado_total',
            $statuses->every(fn ($s) => in_array($s, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED, OrderStatusService::STATUS_KARDEX_OUTPUT, OrderStatusService::STATUS_CANCELLED_SUPPLIER], true)) && ($statuses->contains(OrderStatusService::STATUS_DELIVERED) || $statuses->contains(OrderStatusService::STATUS_KARDEX_OUTPUT) || $statuses->contains(OrderStatusService::STATUS_CLOSED)) => 'entregado_total',
            $statuses->contains(OrderStatusService::STATUS_CANCELLED_SUPPLIER), $statuses->contains(OrderStatusService::STATUS_REPLACEMENT_REQUIRED) => 'con_incidencias',
            $statuses->contains(OrderStatusService::STATUS_DELIVERED), $statuses->contains(OrderStatusService::STATUS_DELIVERED_PARTIAL), $statuses->contains(OrderStatusService::STATUS_KARDEX_OUTPUT), $statuses->contains(OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL), $statuses->contains(OrderStatusService::STATUS_SENT_CUSTOMER) => 'entrega_parcial',
            $statuses->contains(OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH) => 'despacho_parcial_autorizado',
            $statuses->contains(OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED) => 'autorizacion_salida_pendiente',
            $statuses->contains(OrderStatusService::STATUS_WAITING_OTHER_ITEMS), $statuses->contains(OrderStatusService::STATUS_HELD_BY_ADMIN) => 'recepcion_parcial_esperando_restantes',
            $statuses->every(fn ($s) => in_array($s, $warehouseReadyStatuses, true)) => 'recibido_total',
            $statuses->contains(OrderStatusService::STATUS_RECEIVED_WAREHOUSE), $statuses->contains(OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL), $statuses->contains(OrderStatusService::STATUS_KARDEX_ENTRY), $statuses->contains(OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL), $statuses->contains(OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH) => 'recibido_parcial',
            $statuses->contains(OrderStatusService::STATUS_IN_CUSTOMS) => 'en_aduana',
            $statuses->contains(OrderStatusService::STATUS_IN_TRANSIT) => 'en_transito',
            $statuses->contains(OrderStatusService::STATUS_SUPPLIER_CONFIRMED) => 'confirmado_por_proveedor',
            $statuses->contains(OrderStatusService::STATUS_WAITING_SUPPLIER) => 'esperando_confirmacion_proveedor',
            $statuses->contains(OrderStatusService::STATUS_PO_GENERATED), $statuses->contains(OrderStatusService::STATUS_PENDING_PO) => 'oc_generada',
            default => 'pendiente_oc',
        };
    }
}
