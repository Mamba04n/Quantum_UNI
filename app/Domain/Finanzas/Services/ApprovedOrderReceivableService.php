<?php

namespace App\Domain\Finanzas\Services;

use App\Domain\Finanzas\ExchangeRates\Services\ExchangeRateService;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\OrderTracker;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovedOrderReceivableService
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function ensure(
        SaleOrder $saleOrder,
        QuotationRequest $quotation,
        User $user,
        ?OrderTracker $tracker = null
    ): AccountReceivable {
        return DB::transaction(function () use ($saleOrder, $quotation, $user, $tracker): AccountReceivable {
            $saleOrder = SaleOrder::query()
                ->with(['clientOffer', 'orderStatus'])
                ->whereKey($saleOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = AccountReceivable::query()
                ->where('sales_order_id', $saleOrder->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            if (! PurchaseOrder::query()->where('sale_order_id', $saleOrder->id)->exists()) {
                throw ValidationException::withMessages([
                    'receivable' => 'La CXC automatica requiere al menos una orden de compra vinculada.',
                ]);
            }

            $customer = $this->resolveCustomer($saleOrder, $quotation);
            $clientOffer = $saleOrder->clientOffer
                ?: $quotation->offerVersions()->latest('version_number')->first();
            $supportPath = $quotation->client_offer_pdf_path ?: $clientOffer?->pdf_path;

            if (blank($supportPath)) {
                throw ValidationException::withMessages([
                    'receivable' => 'No se puede crear la CXC sin la oferta aprobada que sirve como documento base.',
                ]);
            }

            $orderCurrency = strtoupper((string) ($saleOrder->currency ?: 'USD'));
            $orderTotal = round((float) $saleOrder->total_amount, 2);
            $conversion = $this->exchangeRateService->convert($orderTotal, $orderCurrency, 'NIO');
            $exchangeRate = (float) $conversion['rate'];
            $totalNio = (float) $conversion['amount'];
            $condition = $quotation->payment_terms ?: ($saleOrder->payment_type ?: 'Contado');

            $receivable = $this->financeService->createReceivable([
                'entity_id' => $customer->id,
                'customer_id' => $customer->id,
                'sales_order_id' => $saleOrder->id,
                'client_offer_id' => $clientOffer?->id,
                'order_tracker_id' => $tracker?->id,
                'order_status_id' => $saleOrder->orderStatus?->id,
                'invoice_number' => null,
                'concept' => "Venta de orden {$saleOrder->reference} originada en solicitud {$quotation->reference}",
                'classification' => 'Venta de producto',
                'condition' => $condition,
                'invoice_date' => $saleOrder->date ?: now()->toDateString(),
                'total_amount' => $totalNio,
                'amount_usd' => $orderCurrency === 'USD' ? $orderTotal : null,
                'exchange_rate' => $orderCurrency === 'NIO' ? null : $exchangeRate,
                'currency' => 'NIO',
                'due_date' => $this->dueDate($saleOrder, $condition),
                'support_document_path' => $supportPath,
                'created_by' => $user->id,
            ]);

            ActivityLog::log(
                'finanzas',
                'cxc.created_from_approved_order',
                "CXC {$receivable->receivable_code} creada para la orden {$saleOrder->reference}.",
                [
                    'account_receivable_id' => $receivable->id,
                    'quotation_request_id' => $quotation->id,
                    'sales_order_id' => $saleOrder->id,
                    'order_tracker_id' => $tracker?->id,
                ]
            );

            return $receivable;
        });
    }

    private function resolveCustomer(SaleOrder $saleOrder, QuotationRequest $quotation): Entity
    {
        $customer = $saleOrder->customer_id
            ? Entity::query()->whereKey($saleOrder->customer_id)->first()
            : null;

        if (! $customer) {
            $customer = Entity::query()->firstOrCreate(
                ['name' => $quotation->quote_to_name, 'type' => 'cliente'],
                ['email' => $quotation->quote_to_email]
            );

            $saleOrder->forceFill(['customer_id' => $customer->id])->save();
        }

        return $customer;
    }

    private function dueDate(SaleOrder $saleOrder, string $condition): string
    {
        $days = 0;
        if (preg_match('/(\d+)\s*d/i', $condition, $matches)) {
            $days = (int) $matches[1];
        } elseif (str_contains(strtoupper($condition), 'CRED')) {
            $days = 30;
        }

        return Carbon::parse($saleOrder->date ?: now())->addDays($days)->toDateString();
    }
}
