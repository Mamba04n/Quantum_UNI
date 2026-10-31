<?php

namespace App\Domain\Ventas\Pedidos\Services;

use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\ClientOfferVersionService;
use App\Services\CompanyProfileService;
use App\Services\ConvertQuotationToSaleOrderService;
use App\Services\CustomerService;
use App\Services\ExchangeRateService;
use App\Services\FinanceDocumentService;
use App\Services\FinanceService;
use App\Services\InventoryReservationService;
use App\Services\InventoryService;
use App\Services\KpiService;
use App\Services\MachineComponentLifecycleService;
use App\Services\OrderStatusService;
use App\Services\OrderStatusSyncService;
use App\Services\OrderTrackerService;
use App\Services\PackingListService;
use App\Services\PdfService;
use App\Services\ProductSkuGenerator;
use App\Services\PurchaseOrderDocumentService;
use App\Services\PurchaseOrderProviderDocumentMapper;
use App\Services\PurchaseOrderService;
use App\Services\PurchasePreparationService;
use App\Services\PurchaseReceiptService;
use App\Services\RfqPdfDataMapper;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidService;
use App\Services\SupplierRfqDocumentMapper;
use App\Services\SupplierRfqReviewService;
use App\Services\TicketService;
use App\Services\TrackerDocumentService;

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Entity;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleOrderService
{
    /**
     * Crea una orden de venta, guarda sus ítems e inicializa las cuentas por pagar y cobrar en una transacción de BD.
     *
     * @param array $data Los datos validados de la orden y sus items.
     * @return SaleOrder
     * @throws \Exception
     */
    public function createOrderWithFinancials(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear la Orden de Compra/Venta
            $order = SaleOrder::create([
                'reference' => $data['reference'],
                'date' => $data['date'],
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'client_ruc' => $data['client_ruc'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'provider_name' => $data['provider_name'],
                'provider_ruc' => $data['provider_ruc'] ?? null,
                'payment_type' => $data['payment_type'],
                'subtotal' => $data['subtotal'],
                'tax_percent' => 15.00,
                'tax_amount' => $data['tax_amount'],
                'total_amount' => $data['total_amount'],
                'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                'status' => 'confirmed'
            ]);

            // 2. Crear los renglones (Items) de la orden
            foreach ($data['items'] as $item) {
                SaleOrderItem::create([
                    'sale_order_id' => $order->id,
                    'part_number' => $item['part_number'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
            }

            // 3. Lógica Financiera Automática (CXP a Socios por Financiación)
            if (($data['funding_source'] ?? 'empresa') === 'socio' && !empty($data['socio_id'])) {
                AccountPayable::create([
                    'entity_id'      => $data['socio_id'],
                    'invoice_number' => $order->reference ?? null,
                    'concept'        => 'Préstamo para Pedido de Compra (' . $order->reference . ')',
                    'classification' => 'Préstamo de socio',
                    'condition'      => $data['payment_type'] ?? '30 Días',
                    'invoice_date'   => $order->date,
                    'total_amount'   => $order->total_amount,
                    'amount_usd'     => null,
                    'paid_amount'    => 0,
                    'due_date'       => Carbon::parse($order->date)->addDays(30),
                    'status'         => 'pendiente',
                ]);
            }

            // 4. Lógica Financiera Automática (CXC a Clientes por Deuda del servicio/venta)
            $cliente = Entity::firstOrCreate(
                ['name' => $data['client_name'], 'type' => 'cliente'],
                ['contact_info' => $data['client_email'], 'rfc_ruc' => $data['client_ruc']]
            );

            AccountReceivable::create([
                'entity_id'      => $cliente->id,
                'customer_id'    => $cliente->id,
                'sales_order_id' => $order->id,
                'invoice_number' => $order->reference ?? null,
                'concept'        => 'Facturación de Orden de Compra ' . $order->reference,
                'classification' => 'Venta de producto',
                'condition'      => $data['payment_type'] === 'Crédito' ? '30 Días' : 'Contado',
                'invoice_date'   => $order->date,
                'total_amount'   => $order->total_amount,
                'amount_usd'     => null,
                'paid_amount'    => 0,
                'due_date'       => $data['payment_type'] === 'Crédito' ? Carbon::parse($order->date)->addDays(15) : $order->date,
                'status'         => 'pendiente',
            ]);

            return $order;
        });
    }
}
