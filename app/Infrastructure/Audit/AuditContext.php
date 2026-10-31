<?php

namespace App\Infrastructure\Audit;

class AuditContext
{
    public const MODELS = [
        'finanzas' => ['Payment', 'AccountPayable', 'AccountReceivable', 'Invoice', 'FinanceChangeRequest', 'ExchangeRate', 'PaymentLink', 'PaymentLinkItem'],
        'ventas' => ['SaleOrder', 'SaleOrderItem', 'QuotationRequest', 'QuotationItem', 'ClientOffer', 'ClientOfferItem', 'ClientOfferVersion', 'SupplierBid', 'SupplierBidItem'],
        'compras' => ['PurchaseOrder', 'PurchaseOrderItem', 'SupplierPriceQuotation', 'SupplierPriceItem'],
        'inventario' => ['Product', 'StockMove', 'StockReservation', 'Warehouse', 'Brand', 'Category'],
        'crm' => ['Entity', 'Client', 'EntityContact', 'ClientMachine', 'MachineComponent', 'CrmProspect', 'CommercialOpportunity', 'ProspectingCampaign'],
        'soporte' => ['Ticket', 'TicketMessage', 'TicketAttachment'],
        'admin' => ['User', 'Role', 'Permission', 'EmployeeProfile'],
    ];

    public static function module(?string $class): string
    {
        $name = class_basename($class ?? '');
        foreach (self::MODELS as $module => $names) {
            if (in_array($name, $names, true)) {
                return $module;
            }
        }

        return match (true) {
            str_contains($name, 'OrderStatus'), str_contains($name, 'Tracker') => 'order_status',
            str_contains($name, 'Shipment'), str_contains($name, 'Package') => 'logistica',
            str_contains($name, 'Ticket') => 'soporte',
            str_contains($name, 'Quotation'), str_contains($name, 'Offer'), str_contains($name, 'SupplierBid') => 'ventas',
            default => 'sistema',
        };
    }

    public static function sanitize(array $data, int $depth = 0): array
    {
        if ($depth > 6) {
            return ['omitted' => 'Profundidad máxima'];
        }
        $safe = [];
        foreach (array_slice($data, 0, 100, true) as $key => $value) {
            if (preg_match('/password|token|secret|authorization|cookie|credential|fingerprint|private_key|api_key|idempotency_key|account_number|card_number|cvv|file_content|payload/i', (string) $key)) {
                $safe[$key] = '[Protegido]';
            } elseif (is_array($value)) {
                $safe[$key] = self::sanitize($value, $depth + 1);
            } elseif (is_string($value)) {
                // JSON attributes must be scrubbed recursively too.
                $decoded = json_decode($value, true);
                $safe[$key] = is_array($decoded) ? self::sanitize($decoded, $depth + 1) : mb_substr($value, 0, 2000);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    public static function path(?string $path): string
    {
        return preg_replace('~((?:reset-password|reset|client-pdf|track|tracking|pay|checkout)/)[^/]+~i', '$1[Protegido]', explode('?', $path ?? '')[0]);
    }
}
