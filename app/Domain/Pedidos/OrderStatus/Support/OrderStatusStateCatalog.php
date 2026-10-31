<?php

namespace App\Domain\Pedidos\OrderStatus\Support;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Models\OrderStatusItem;

class OrderStatusStateCatalog
{
    public function requiredDocumentForStatus(string $status): ?string
    {
        return match ($status) {
            OrderStatusService::STATUS_WAITING_SUPPLIER, OrderStatusService::STATUS_PO_GENERATED => 'purchase_order_provider_pdf',
            OrderStatusService::STATUS_IN_TRANSIT => 'supplier_confirmation',
            OrderStatusService::STATUS_IN_CUSTOMS => 'shipping_document',
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE, OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL, OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH => 'warehouse_receipt',
            OrderStatusService::STATUS_SENT_CUSTOMER => 'delivery_logistics_document',
            OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_DELIVERED_PARTIAL, OrderStatusService::STATUS_CLOSED => 'delivery_confirmation',
            default => null,
        };
    }

    public function phaseForItemStatus(string $status): string
    {
        return match ($status) {
            OrderStatusService::STATUS_PENDING_ORDER, OrderStatusService::STATUS_ORDER_GENERATED, OrderStatusService::STATUS_ORDER_SENT, OrderStatusService::STATUS_PENDING_PO, OrderStatusService::STATUS_PO_GENERATED => OrderStatusService::PHASE_PURCHASE,
            OrderStatusService::STATUS_WAITING_SUPPLIER => OrderStatusService::PHASE_SUPPLIER_CONFIRMATION,
            OrderStatusService::STATUS_SUPPLIER_CONFIRMED, OrderStatusService::STATUS_PAYMENT_CONFIRMED, OrderStatusService::STATUS_SUPPLIER_DISPATCHED => OrderStatusService::PHASE_TRANSIT,
            OrderStatusService::STATUS_IN_TRANSIT => OrderStatusService::PHASE_CUSTOMS,
            OrderStatusService::STATUS_IN_CUSTOMS, OrderStatusService::STATUS_CUSTOMS_RELEASED => OrderStatusService::PHASE_WAREHOUSE,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE, OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL => OrderStatusService::PHASE_KARDEX_OUTPUT,
            OrderStatusService::STATUS_KARDEX_ENTRY, OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL, OrderStatusService::STATUS_RESERVED_CUSTOMER, OrderStatusService::STATUS_WAITING_OTHER_ITEMS, OrderStatusService::STATUS_HELD_BY_ADMIN => OrderStatusService::PHASE_KARDEX_OUTPUT,
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH, OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED, OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH => OrderStatusService::PHASE_KARDEX_OUTPUT,
            // Una salida de Kardex ya descontó el inventario. El siguiente paso
            // operativo es el despacho al cliente, nunca volver a sacar Kardex.
            OrderStatusService::STATUS_KARDEX_OUTPUT, OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL => OrderStatusService::PHASE_LOGISTICS,
            // Una partida ya despachada esta en ruta: el siguiente paso es confirmar
            // la entrega, no intentar registrar otra salida de Kardex.
            OrderStatusService::STATUS_SENT_CUSTOMER => OrderStatusService::PHASE_DELIVERY,
            OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_DELIVERED_PARTIAL, OrderStatusService::STATUS_CLOSED => OrderStatusService::PHASE_DELIVERY,
            default => 'general',
        };
    }

    public function phaseForGeneralStatus(string $status): string
    {
        return match ($status) {
            'esperando_confirmacion_proveedor' => OrderStatusService::PHASE_SUPPLIER_CONFIRMATION,
            'confirmado_por_proveedor' => OrderStatusService::PHASE_TRANSIT,
            'en_transito' => OrderStatusService::PHASE_CUSTOMS,
            'en_aduana' => OrderStatusService::PHASE_WAREHOUSE,
            'recibido_parcial', 'recibido_total', 'en_bodega', 'recepcion_parcial_esperando_restantes' => OrderStatusService::PHASE_KARDEX_OUTPUT,
            'autorizacion_salida_pendiente', 'despacho_parcial_autorizado' => OrderStatusService::PHASE_KARDEX_OUTPUT,
            'entrega_parcial' => OrderStatusService::PHASE_LOGISTICS,
            'entregado_total', 'cerrado' => OrderStatusService::PHASE_DELIVERY,
            'con_incidencias', 'cancelado_parcial', 'cancelado_total' => 'incidencias',
            default => OrderStatusService::PHASE_PURCHASE,
        };
    }

    public function progressForStatus(string $status): int
    {
        return [
            OrderStatusService::STATUS_PENDING_ORDER => 5,
            OrderStatusService::STATUS_ORDER_GENERATED => 10,
            OrderStatusService::STATUS_ORDER_SENT => 15,
            OrderStatusService::STATUS_PENDING_PO => 5,
            OrderStatusService::STATUS_PO_GENERATED => 12,
            OrderStatusService::STATUS_WAITING_SUPPLIER => 18,
            OrderStatusService::STATUS_SUPPLIER_CONFIRMED => 28,
            OrderStatusService::STATUS_PAYMENT_CONFIRMED => 34,
            OrderStatusService::STATUS_SUPPLIER_DISPATCHED => 38,
            OrderStatusService::STATUS_IN_TRANSIT => 42,
            OrderStatusService::STATUS_IN_CUSTOMS => 54,
            OrderStatusService::STATUS_CUSTOMS_RELEASED => 60,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL => 62,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE => 66,
            OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL => 70,
            OrderStatusService::STATUS_KARDEX_ENTRY => 74,
            OrderStatusService::STATUS_RESERVED_CUSTOMER => 78,
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH => 76,
            OrderStatusService::STATUS_HELD_BY_ADMIN => 76,
            OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED => 77,
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS => 76,
            OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH => 79,
            OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL => 80,
            OrderStatusService::STATUS_KARDEX_OUTPUT => 84,
            OrderStatusService::STATUS_SENT_CUSTOMER => 91,
            OrderStatusService::STATUS_DELIVERED_PARTIAL => 94,
            OrderStatusService::STATUS_DELIVERED => 100,
            OrderStatusService::STATUS_CLOSED => 100,
            OrderStatusService::STATUS_CANCELLED_SUPPLIER => 0,
            OrderStatusService::STATUS_REPLACEMENT_REQUIRED => 15,
            OrderStatusService::STATUS_REPLACEMENT_QUOTING => 20,
            OrderStatusService::STATUS_REPLACEMENT_APPROVED => 28,
        ][$status] ?? 0;
    }

    public function humanStatus(string $status): string
    {
        return [
            OrderStatusService::STATUS_PENDING_PO => 'Pendiente de OC',
            OrderStatusService::STATUS_PO_GENERATED => 'OC generada',
            OrderStatusService::STATUS_WAITING_SUPPLIER => 'Esperando confirmacion del proveedor',
            OrderStatusService::STATUS_SUPPLIER_CONFIRMED => 'Confirmado por proveedor',
            OrderStatusService::STATUS_IN_TRANSIT => 'En transito',
            OrderStatusService::STATUS_IN_CUSTOMS => 'En aduana',
            OrderStatusService::STATUS_CUSTOMS_RELEASED => 'Liberado de aduana',
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL => 'Recibido parcial en bodega',
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE => 'Recibido en bodega',
            OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL => 'Entrada Kardex parcial',
            OrderStatusService::STATUS_KARDEX_ENTRY => 'Entrada Kardex total',
            OrderStatusService::STATUS_RESERVED_CUSTOMER => 'Reservado para cliente',
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH => 'Disponible para despacho',
            OrderStatusService::STATUS_HELD_BY_ADMIN => 'Retenido en bodega',
            OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED => 'Autorización de salida solicitada',
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS => 'Esperando otros productos',
            OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH => 'Salida parcial autorizada',
            OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL => 'Salida Kardex parcial',
            OrderStatusService::STATUS_KARDEX_OUTPUT => 'Salida Kardex total',
            OrderStatusService::STATUS_SENT_CUSTOMER => 'Enviado al cliente',
            OrderStatusService::STATUS_DELIVERED_PARTIAL => 'Entrega parcial',
            OrderStatusService::STATUS_DELIVERED => 'Entregado',
            OrderStatusService::STATUS_CLOSED => 'Cerrado',
            OrderStatusService::STATUS_CANCELLED_SUPPLIER => 'Cancelado por proveedor',
            OrderStatusService::STATUS_REPLACEMENT_REQUIRED => 'Reemplazo requerido',
        ][$status] ?? str($status)->replace('_', ' ')->headline()->toString();
    }

    public function humanDocument(string $documentType): string
    {
        return str($documentType)->replace('_', ' ')->headline()->toString();
    }

    public function itemLabel(OrderStatusItem $item): string
    {
        return $item->part_number ?: ($item->part_name ?: '#'.$item->id);
    }
}
