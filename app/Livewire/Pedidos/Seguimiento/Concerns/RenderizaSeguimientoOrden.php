<?php

namespace App\Livewire\Pedidos\Seguimiento\Concerns;

use App\Models\Entity;
use App\Services\OrderStatusService;

trait RenderizaSeguimientoOrden
{
    public function render()
    {
        $selectedItem = $this->selectedItemId
            ? $this->orderStatus->items->firstWhere('id', $this->selectedItemId)
            : null;

        $visibleItems = $this->visibleItems();
        $selectedGroupItems = $this->selectedGroupKey && isset($this->itemGroups[$this->selectedGroupKey])
            ? $this->orderStatus->items->whereIn('id', $this->itemGroups[$this->selectedGroupKey]['item_ids'])->values()
            : collect();

        return view('livewire.pedidos.order-status-show', [
            'selectedItem' => $selectedItem,
            'replacementSuppliers' => Entity::query()->where('type', 'proveedor')->where('status', 'activo')->orderBy('name')->get(['id', 'name', 'contact_name']),
            'selectedGroupItems' => $selectedGroupItems,
            'groupedItemIds' => $this->groupedItemIds(),
            'visibleItems' => $visibleItems,
            'financialDocuments' => $this->financialDocuments(),
            'purchaseOrderDocuments' => $this->purchaseOrderDocuments(),
            'statusDocuments' => $this->statusDocuments(),
            'phaseDefinitions' => $this->phaseDefinitions(),
            'activePhaseData' => $this->phaseDefinitions()[$this->activePhase] ?? $this->phaseDefinitions()[OrderStatusService::PHASE_PURCHASE],
            'currentPhaseData' => $this->phaseDefinitions()[$this->orderStatus->administrative_phase] ?? $this->phaseDefinitions()[OrderStatusService::PHASE_PURCHASE],
            'isNextStepPhase' => $this->activePhase !== $this->orderStatus->administrative_phase,
            'activePhaseDocuments' => $this->statusDocuments()->where('phase', $this->activePhase)->values(),
            'statuses' => [
                OrderStatusService::STATUS_WAITING_SUPPLIER => 'Esperando confirmacion proveedor',
                OrderStatusService::STATUS_SUPPLIER_CONFIRMED => 'Confirmado proveedor',
                OrderStatusService::STATUS_IN_TRANSIT => 'En transito',
                OrderStatusService::STATUS_IN_CUSTOMS => 'En aduana',
                OrderStatusService::STATUS_CUSTOMS_RELEASED => 'Liberado aduana',
                OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL => 'Recibido parcial bodega',
                OrderStatusService::STATUS_RECEIVED_WAREHOUSE => 'Recibido en bodega',
                OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL => 'Entrada Kardex parcial',
                OrderStatusService::STATUS_KARDEX_ENTRY => 'Entrada Kardex total',
                OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH => 'Disponible para despacho',
                OrderStatusService::STATUS_WAITING_OTHER_ITEMS => 'Esperando otros productos',
                OrderStatusService::STATUS_HELD_BY_ADMIN => 'Retenido en bodega',
                OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED => 'Autorización de salida solicitada',
                OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH => 'Salida parcial autorizada',
                OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL => 'Salida Kardex parcial',
                OrderStatusService::STATUS_KARDEX_OUTPUT => 'Salida Kardex total',
                OrderStatusService::STATUS_SENT_CUSTOMER => 'Enviado al cliente',
                OrderStatusService::STATUS_DELIVERED_PARTIAL => 'Entregado parcial',
                OrderStatusService::STATUS_DELIVERED => 'Entregado',
                OrderStatusService::STATUS_CLOSED => 'Cerrado',
            ],
            'documentTypes' => [
                'purchase_order_pdf' => 'PDF OC / Pedido extranjero',
                'purchase_order_provider_pdf' => 'PDF OC proveedor',
                'supplier_confirmation' => 'Confirmacion proveedor',
                'shipping_document' => 'Comprobante de envio',
                'customs_document' => 'Documento aduana',
                'warehouse_receipt' => 'Recepcion bodega',
                'warehouse_product_photo' => 'Foto producto recibido',
                'kardex_entry_document' => 'Documento entrada Kardex',
                'kardex_output_document' => 'Factura de salida Kardex',
                'delivery_logistics_document' => 'Foto/comprobante de envio',
                'packing_list_pdf' => 'Packing list PDF',
                'delivery_confirmation' => 'Comprobante de entrega',
            ],
        ]);
    }
}
