<?php

namespace App\Domain\Ventas\Pedidos\Services;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\Tracking\Services\OrderTrackerService;
use App\Domain\Shared\Sequences\DocumentSequenceService;
use App\Domain\Ventas\FlujoTrabajo\Services\SalesWorkflowService;
use App\Models\ActivityLog;
use App\Models\ClientOfferVersion;
use App\Models\Entity;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertQuotationToSaleOrderService
{
    public function __construct(private readonly OrderTrackerService $trackerService) {}

    public function convert(QuotationRequest $quotation, ?User $user = null): SaleOrder
    {
        $user = $user ?: auth()->user();
        if (! $user || (! $user->can('order.generate') && ! $user->can('direct_order.create') && ! $user->can('order_approval.approve'))) {
            throw ValidationException::withMessages(['order' => 'No tienes permiso para generar orden desde oferta aprobada.']);
        }

        if ($quotation->sale_order_id) {
            throw ValidationException::withMessages(['order' => 'Esta oferta ya fue convertida en orden.']);
        }

        $quotation->loadMissing('items.product', 'offerVersions.items', 'user');
        $this->assertCanGenerateOrder($quotation);

        return DB::transaction(function () use ($quotation, $user) {
            // Lock and revalidate the quotation inside the transaction. Two
            // users may open it, but only one conversion can commit.
            $quotation = QuotationRequest::whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quotation->sale_order_id) {
                return SaleOrder::with('items')->findOrFail($quotation->sale_order_id);
            }

            $quotation->load('items.product', 'offerVersions.items', 'user');
            $this->assertCanGenerateOrder($quotation);
            $approvedItems = $quotation->items->where('is_approved', true);
            $clientOffer = $this->latestClientOffer($quotation);

            $subtotal = (float) $approvedItems->sum(fn ($line) => round((float) $line->quantity * (float) $line->unit_price, 2));
            $taxPercent = 15.0;
            $taxAmount = round($subtotal * ($taxPercent / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $reference = app(DocumentSequenceService::class)
                ->formattedNext('sale_order', 'OC', 4);
            $customer = Entity::where('type', 'cliente')->where('name', $quotation->quote_to_name)->first();
            $clientEmail = $quotation->quote_to_email ?: $quotation->quote_to_email_alt;
            $shippingAddress = implode("\n\n", array_filter([
                $quotation->consignee_name,
                $quotation->consignee_handling_notes,
                $quotation->consignee_address,
            ]));

            $order = SaleOrder::create([
                'reference' => $reference,
                'date' => optional($quotation->date)->format('Y-m-d') ?: now()->format('Y-m-d'),
                'customer_id' => $customer?->id,
                'company_issuer' => $quotation->company_issuer ?: 'quantum',
                'client_offer_version_id' => $clientOffer?->id,
                'client_name' => $quotation->quote_to_name,
                'client_email' => $clientEmail,
                'client_ruc' => null,
                'shipping_address' => $shippingAddress ?: null,
                'provider_name' => null,
                'provider_ruc' => null,
                'payment_type' => $this->inferPaymentType($quotation->payment_terms),
                'subtotal' => $subtotal,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'currency' => strtoupper((string) ($clientOffer?->currency ?: 'USD')),
                'status' => 'generated',
                'source_type' => 'approved_offer',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'generated_by' => $user->id,
                'generated_at' => now(),
                'terms_and_conditions' => $this->termsForQuotation($quotation, $user),
                'notes' => 'Generada desde oferta aprobada '.$quotation->reference.'.',
            ]);

            $hasPurchaseRequired = false;

            foreach ($approvedItems as $line) {
                $qty = (float) $line->quantity;
                $unit = (float) $line->unit_price;
                $product = $this->findProductForLine($line);
                $sourceType = $this->sourceTypeForLine($product, $qty);
                $inventoryStatus = match ($sourceType) {
                    'inventory' => 'in_inventory',
                    'supplier', 'foreign_order' => 'pending_entry',
                    default => 'pending_creation',
                };
                $purchaseStatus = $sourceType === 'inventory' ? 'not_required' : 'required';
                $hasPurchaseRequired = $hasPurchaseRequired || $purchaseStatus === 'required';

                SaleOrderItem::create([
                    'sale_order_id' => $order->id,
                    'client_offer_item_id' => $clientOffer?->items?->firstWhere('quotation_request_item_id', $line->id)?->id,
                    'quotation_request_item_id' => $line->id,
                    'product_id' => $product?->id,
                    'part_number' => $line->part_number,
                    'replacement_part' => null,
                    'description' => $line->part_name ?: $line->description,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total_price' => round($qty * $unit, 2),
                    'brand' => $line->brand,
                    'model' => $line->model ?? null,
                    'commerce' => null,
                    'purchase_date' => null,
                    'item_status' => 'Generada',
                    'source_type' => $sourceType,
                    'inventory_status' => $inventoryStatus,
                    'purchase_status' => $purchaseStatus,
                ]);

                if ($purchaseStatus === 'required') {
                    ActivityLog::log('ventas', 'purchase_required', "Partida {$line->part_number} requiere compra o creacion de producto.", [
                        'quote_id' => $quotation->id,
                        'order_id' => $order->id,
                        'quote_item_id' => $line->id,
                    ]);
                    ActivityLog::log('inventario', 'inventory_entry_pending', "Partida {$line->part_number} pendiente de entrada Kardex.", [
                        'order_id' => $order->id,
                        'quote_item_id' => $line->id,
                    ]);
                }
            }

            if ($hasPurchaseRequired) {
                $order->update(['status' => 'pending_purchase']);
            }

            $oldStatus = $quotation->status;
            $quotation->update([
                'sale_order_id' => $order->id,
                'status' => QuotationRequest::STATUS_APPROVED_TOTAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ]);

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            $tracker->update([
                'operational_phase' => 'order_generation',
                'operational_status' => $order->status,
                'main_reference' => $order->reference,
            ]);
            $this->trackerService->addReference($tracker, 'sales_order', $order->reference, 'sale_orders', $order->id);
            $this->trackerService->recordActivity(
                $tracker,
                'sales_order.generated',
                "Orden {$order->reference} generada desde oferta aprobada {$quotation->reference}",
                'order_generation',
                'sale_orders',
                $order->id,
                $oldStatus,
                $order->status,
                $user
            );

            if ($hasPurchaseRequired) {
                $this->trackerService->recordActivity(
                    $tracker,
                    'purchase_required',
                    "Orden {$order->reference} contiene partidas pendientes de compra o inventario",
                    'purchase',
                    'sale_orders',
                    $order->id,
                    null,
                    'pending_purchase',
                    $user
                );
            }

            ActivityLog::log('ventas', 'sales_order.generated', "Orden {$order->reference} generada desde oferta aprobada {$quotation->reference}", [
                'quote_id' => $quotation->id,
                'order_id' => $order->id,
                'client_offer_version_id' => $clientOffer?->id,
            ]);

            app(OrderStatusService::class)->ensureForSaleOrder($order->fresh('items'), $user);

            return $order->fresh(['items']);
        }, (int) config('concurrency.transaction_attempts', 3));
    }

    private function assertCanGenerateOrder(QuotationRequest $quotation): void
    {
        if (! in_array($quotation->status, [
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
        ], true)) {
            throw ValidationException::withMessages(['order' => 'No se puede generar orden si la oferta no esta aprobada.']);
        }

        if (! $quotation->client_offer_pdf_path && $quotation->offerVersions->isEmpty()) {
            throw ValidationException::withMessages(['order' => 'No se puede generar orden sin oferta cliente generada.']);
        }

        if (blank($quotation->quote_to_name)) {
            throw ValidationException::withMessages(['order' => 'No se puede generar orden sin cliente.']);
        }

        $approvedItems = $quotation->items->where('is_approved', true);
        if ($approvedItems->isEmpty()) {
            throw ValidationException::withMessages(['order' => 'No hay partidas aprobadas para generar orden.']);
        }

        foreach ($approvedItems as $line) {
            if ((float) $line->quantity <= 0) {
                throw ValidationException::withMessages(['order' => "La partida {$this->lineLabel($line)} tiene cantidad invalida."]);
            }
            if ((float) $line->unit_price <= 0) {
                throw ValidationException::withMessages(['order' => "La partida {$this->lineLabel($line)} no tiene precio final."]);
            }
        }
    }

    private function latestClientOffer(QuotationRequest $quotation): ?ClientOfferVersion
    {
        return $quotation->offerVersions->sortByDesc('version_number')->first();
    }

    private function findProductForLine($line): ?Product
    {
        if ($line->product_id) {
            return Product::find($line->product_id);
        }

        if (empty($line->part_number)) {
            return null;
        }

        return Product::query()
            ->where('sku', $line->part_number)
            ->orWhere('part_number', $line->part_number)
            ->first();
    }

    private function lineLabel($line): string
    {
        return $line->part_number ?: ($line->part_name ?: 'sin referencia');
    }

    private function sourceTypeForLine(?Product $product, float $quantity): string
    {
        if (! $product) {
            return 'manual_pending_creation';
        }

        return (float) ($product->stock_on_hand ?? 0) >= $quantity ? 'inventory' : 'supplier';
    }

    private function inferPaymentType(?string $paymentTerms): string
    {
        $terms = strtoupper((string) $paymentTerms);

        return str_contains($terms, 'CRED') || str_contains($terms, '30') || str_contains($terms, '15')
            ? 'Credito'
            : 'Contado';
    }

    private function termsForQuotation(QuotationRequest $quotation, User $user): ?string
    {
        return implode(' | ', array_filter([
            $quotation->ship_via ? 'Envio: '.$quotation->ship_via : null,
            $quotation->shipping_conditions ? 'Cond. envio: '.$quotation->shipping_conditions : null,
            $quotation->delivery_date ? 'Entrega deseada: '.$quotation->delivery_date->format('d/m/Y') : null,
            $quotation->department ? 'Depto.: '.$quotation->department : null,
            $quotation->requested_by ? 'Solicitado por: '.$quotation->requested_by : null,
            'Aprobado por: '.$user->name,
        ]));
    }
}
