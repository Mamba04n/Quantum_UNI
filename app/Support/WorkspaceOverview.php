<?php

namespace App\Support;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ClientOfferReview;
use App\Models\Entity;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Models\Ticket;
use App\Models\User;

final class WorkspaceOverview
{
    /** Counts only: no financial amounts or record details are loaded by the launchpad. */
    public function metrics(User $user, ?string $area, WorkspaceNavigation $navigation): array
    {
        $areaItems = $area === null ? null : ($navigation->areas($user)[$area]['items'] ?? []);
        $definitions = [
            ['areas' => [null, 'ventas', 'compras', 'reportes'], 'route' => 'sales.quotations.licitacion', 'label' => 'Solicitudes para licitar', 'context' => 'Pendientes de RFQ', 'icon' => 'document', 'pending' => true,
                'query' => fn () => QuotationRequest::whereNull('sale_order_id')->whereIn('status', [QuotationRequest::STATUS_RECEIVED, QuotationRequest::STATUS_IN_REVIEW])],
            ['areas' => [null, 'ventas', 'compras'], 'route' => 'sales.quotations.corrections', 'label' => 'Ofertas por corregir', 'context' => 'Cambios solicitados', 'icon' => 'edit', 'pending' => true,
                'query' => fn () => QuotationRequest::whereIn('status', [QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED, QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED])],
            ['areas' => [null, 'finanzas', 'reportes'], 'route' => 'finance.cxc', 'label' => 'Cuentas por cobrar vencidas', 'context' => 'Con saldo pendiente', 'icon' => 'wallet', 'pending' => true,
                'query' => fn () => AccountReceivable::where('status', '!=', 'pagado')->whereColumn('total_amount', '>', 'paid_amount')->whereDate('due_date', '<', today())],
            ['areas' => [null, 'finanzas', 'reportes'], 'route' => 'finance.cxp', 'label' => 'Cuentas por pagar vencidas', 'context' => 'Con saldo pendiente', 'icon' => 'wallet', 'pending' => true,
                'query' => fn () => AccountPayable::where('status', '!=', 'pagado')->whereColumn('total_amount', '>', 'paid_amount')->whereDate('due_date', '<', today())],
            ['areas' => [null, 'compras', 'operaciones'], 'route' => 'admin.rfq-reviews', 'label' => 'RFQ por revisar', 'context' => 'Revisión administrativa', 'icon' => 'document', 'pending' => true,
                'query' => fn () => SupplierRfqDocument::where('status', SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW)],
            ['areas' => [null, 'ventas', 'operaciones'], 'route' => 'admin.offer-reviews', 'label' => 'Ofertas por aprobar', 'context' => 'Revisión administrativa', 'icon' => 'check', 'pending' => true,
                'query' => fn () => ClientOfferReview::where('status', ClientOfferReview::STATUS_PENDING)],
            ['areas' => [null, 'bodega'], 'route' => 'inventory.products', 'label' => 'Productos activos', 'context' => 'Catálogo de inventario', 'icon' => 'box', 'pending' => false,
                'query' => fn () => Product::where('active', true)],
            ['areas' => [null, 'clientes'], 'route' => 'clients.index', 'label' => 'Clientes registrados', 'context' => 'Directorio comercial', 'icon' => 'users', 'pending' => false,
                'query' => fn () => Entity::where('type', 'cliente')],
            ['areas' => [null, 'proveedores', 'compras'], 'route' => 'admin.suppliers', 'label' => 'Proveedores registrados', 'context' => 'Directorio de proveedores', 'icon' => 'building', 'pending' => false,
                'query' => fn () => Entity::where('type', 'proveedor')],
            ['areas' => [null, 'clientes', 'operaciones'], 'route' => 'admin.tickets', 'label' => 'Tickets en atención', 'context' => 'Abiertos o en proceso', 'icon' => 'message', 'pending' => true,
                'query' => fn () => Ticket::whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS])],
        ];

        $metrics = [];
        foreach ($definitions as $definition) {
            if (! in_array($area, $definition['areas'], true) || ! $navigation->allows($user, $definition['route'])) {
                continue;
            }
            if ($areaItems !== null && ! isset($areaItems[$navigation->currentModule($definition['route'])])) {
                continue;
            }
            $definition['value'] = $definition['query']()->count();
            unset($definition['query'], $definition['areas']);
            $metrics[] = $definition;
            if (count($metrics) === 3) {
                break;
            }
        }

        return $metrics;
    }
}
