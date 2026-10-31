<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Actions;

use App\Domain\Ventas\LicitacionesProveedor\Queries\ValidateSupplierSelectionsForOffer;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidActivity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSupplierBidSelection
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly ValidateSupplierSelectionsForOffer $selectionValidator,
        private readonly SupplierBidActivity $activity,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function selectItem(SupplierBidResponseItem $responseItem, User $user, ?float $quantity = null): SupplierBidSelection
    {
        return DB::transaction(function () use ($responseItem, $user, $quantity) {
            $responseItem->loadMissing('response.supplier', 'quotationItem.quotationRequest');

            if (! $responseItem->is_selectable) {
                throw ValidationException::withMessages(['selection' => 'No puedes seleccionar partidas no cotizadas o sin precio valido.']);
            }

            $selectedQuantity = $quantity ?: (float) $responseItem->quoted_quantity;
            if ($selectedQuantity <= 0 || $selectedQuantity > (float) $responseItem->quoted_quantity) {
                throw ValidationException::withMessages(['selection' => 'La cantidad seleccionada no puede superar la cantidad cotizada.']);
            }

            $quoteItem = $responseItem->quotationItem;
            if (!$quoteItem) {
                throw ValidationException::withMessages(['selection' => 'Vincula esta partida independiente con una partida de la solicitud antes de seleccionarla para oferta.']);
            }
            $request = $quoteItem->quotationRequest;
            $response = $responseItem->response;

            if ($selectedQuantity > (float) $quoteItem->quantity) {
                throw ValidationException::withMessages(['selection' => 'La cantidad seleccionada no puede superar la cantidad solicitada por el cliente.']);
            }

            $priceUsd = (float) ($responseItem->preliquidated_unit_price ?? 0);
            $priceNio = (float) ($responseItem->preliquidated_unit_price_nio ?? 0);
            if ($priceUsd <= 0 && $priceNio <= 0 && ! empty($response->preliquidation)) {
                $calc = app(\App\Services\PreliquidationCalculator::class)->forResponse($response);
                $rowCalc = $calc['rows'][$responseItem->id] ?? null;
                if ($rowCalc) {
                    $priceUsd = (float) ($rowCalc['assigned_unit'] ?: ($rowCalc['price'] ?? 0));
                    $priceNio = (float) ($rowCalc['assigned_unit_nio'] ?: ($rowCalc['price_nio'] ?? 0));
                }
            }

            $selectionData = [
                'supplier_bid_id' => $response->supplier_bid_id,
                'customer_request_item_id' => $quoteItem->id,
                'supplier_bid_response_id' => $response->id,
                'supplier_bid_response_item_id' => $responseItem->id,
                'supplier_id' => $response->supplier_id,
                'selected_supplier_id' => $response->supplier_id,
                'selected_quantity' => $selectedQuantity,
                'selected_unit_cost' => $responseItem->unit_cost,
                'currency' => $responseItem->currency,
                'selected_brand' => $responseItem->quoted_brand,
                'selected_model' => $responseItem->quoted_model,
                'selected_part_number' => $responseItem->quoted_part_number,
                'estimated_arrival_date' => $responseItem->estimated_arrival_date,
                'delivery_time' => $responseItem->delivery_time ?: $response->general_delivery_time,
                'warranty' => $responseItem->warranty ?: $response->general_warranty,
                'is_alternative' => (bool) ($responseItem->is_alternative ?? false),
                'selected_by' => $user->id,
                'selected_at' => now(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('supplier_bid_selections', 'selected_unit_price')) {
                $selectionData['selected_unit_price'] = $priceUsd > 0 ? $priceUsd : null;
                $selectionData['selected_unit_price_nio'] = $priceNio > 0 ? $priceNio : null;
            }

            $selection = SupplierBidSelection::updateOrCreate(
                [
                    'quotation_request_id' => $request->id,
                    'quotation_request_item_id' => $quoteItem->id,
                ],
                $selectionData
            );

            $targetCurrency = strtoupper((string) ($request->currency ?: 'NIO'));
            $unitPrice = ($targetCurrency === 'USD' && $priceUsd > 0)
                ? $priceUsd
                : ($priceNio > 0 ? $priceNio : ($priceUsd > 0 ? round($priceUsd * 37.0, 2) : (float) $quoteItem->unit_price));

            $itemPayload = [
                'selected_supplier_id' => $response->supplier_id,
                'supplier_delivery_time' => $selection->delivery_time ?: optional($selection->estimated_arrival_date)->format('Y-m-d'),
                'supplier_response_notes' => $responseItem->internal_comment,
                'is_approved' => false,
                'supplier_selection_status' => 'selected',
                'supplier_selection_exclusion_reason' => null,
                'supplier_selection_excluded_by' => null,
                'supplier_selection_excluded_at' => null,
            ];
            if ($unitPrice > 0) {
                $itemPayload['unit_price'] = round($unitPrice, 2);
                $itemPayload['line_total'] = round((float) $quoteItem->quantity * $unitPrice, 2);
            }
            $quoteItem->forceFill($itemPayload)->save();

            // A supplier response is normally USD. Store both the original
            // quote and its NIO equivalent as soon as it is selected, so the
            // margin never compares C$ sales against raw USD cost.
            $quoteItem = $this->exchangeRateService->syncSupplierCostInNio(
                $quoteItem,
                $responseItem->unit_cost,
                $responseItem->currency ?: $response->currency,
            );

            $oldStatus = $request->status;
            $request->forceFill([
                'status' => QuotationRequest::STATUS_SUPPLIER_SELECTED,
                'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
            ])->save();

            // La selección individual no necesita hidratar comentarios, adjuntos ni
            // aprobaciones del tracker. Con órdenes grandes esa carga dominaba el clic.
            $tracker = $this->trackerService->trackerForQuotation($request, false);
            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.item_selected',
                "Partida {$this->activity->lineLabel($quoteItem)} seleccionada con {$response->supplier?->name}",
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'supplier_bid_selections',
                $selection->id,
                $oldStatus,
                $request->status,
                $user
            );

            $this->activity->logGlobal('supplier_bid.item_selected', "Partida {$this->activity->lineLabel($quoteItem)} seleccionada para {$request->reference}", [
                'quote_id' => $request->id,
                'quote_item_id' => $quoteItem->id,
                'supplier_bid_response_item_id' => $responseItem->id,
                'supplier_bid_selection_id' => $selection->id,
            ]);

            return $selection;
        });
    }

    /**
     * Crea una selección base por partida para alimentar la oferta al cliente.
     * No representa la decisión final de compra: Compras puede sustituirla
     * durante la preparación de la OC.
     */
    public function selectLowestCostItemsForOffer(QuotationRequest $request, User $user): int
    {
        $request->loadMissing('items.supplierBidResponseItems.response.supplier');

        return DB::transaction(function () use ($request, $user): int {
            $selected = 0;
            $missingItems = [];

            foreach ($request->items as $quoteItem) {
                $options = $quoteItem->supplierBidResponseItems
                    ->filter(fn (SupplierBidResponseItem $item) => $item->is_selectable)
                    ->map(function (SupplierBidResponseItem $item): array {
                        $currency = $item->currency ?: $item->response?->currency ?: 'USD';
                        $converted = $this->exchangeRateService->convert($item->unit_cost, $currency, 'NIO');

                        return ['item' => $item, 'cost_nio' => (float) $converted['amount']];
                    })
                    ->sortBy('cost_nio')
                    ->values();

                $responseItem = $options->first()['item'] ?? null;
                if (! $responseItem) {
                    $missingItems[] = $quoteItem->part_number
                        ?: $quoteItem->part_name
                        ?: "Partida {$quoteItem->id}";

                    continue;
                }

                $response = $responseItem->response;
                $selectedQuantity = min(
                    (float) $quoteItem->quantity,
                    (float) $responseItem->quoted_quantity,
                );

                $priceUsd = (float) ($responseItem->preliquidated_unit_price ?? 0);
                $priceNio = (float) ($responseItem->preliquidated_unit_price_nio ?? 0);
                if ($priceUsd <= 0 && $priceNio <= 0 && ! empty($response->preliquidation)) {
                    $calc = app(\App\Services\PreliquidationCalculator::class)->forResponse($response);
                    $rowCalc = $calc['rows'][$responseItem->id] ?? null;
                    if ($rowCalc) {
                        $priceUsd = (float) ($rowCalc['assigned_unit'] ?: ($rowCalc['price'] ?? 0));
                        $priceNio = (float) ($rowCalc['assigned_unit_nio'] ?: ($rowCalc['price_nio'] ?? 0));
                    }
                }

                $selectionData = [
                    'supplier_bid_id' => $response->supplier_bid_id,
                    'customer_request_item_id' => $quoteItem->id,
                    'supplier_bid_response_id' => $response->id,
                    'supplier_bid_response_item_id' => $responseItem->id,
                    'supplier_id' => $response->supplier_id,
                    'selected_supplier_id' => $response->supplier_id,
                    'selected_quantity' => $selectedQuantity,
                    'selected_unit_cost' => $responseItem->unit_cost,
                    'currency' => $responseItem->currency ?: $response->currency,
                    'selected_brand' => $responseItem->quoted_brand,
                    'selected_model' => $responseItem->quoted_model,
                    'selected_part_number' => $responseItem->quoted_part_number,
                    'estimated_arrival_date' => $responseItem->estimated_arrival_date,
                    'delivery_time' => $responseItem->delivery_time ?: $response->general_delivery_time,
                    'warranty' => $responseItem->warranty ?: $response->general_warranty,
                    'is_alternative' => (bool) ($responseItem->is_alternative ?? false),
                    'selected_by' => $user->id,
                    'selected_at' => now(),
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('supplier_bid_selections', 'selected_unit_price')) {
                    $selectionData['selected_unit_price'] = $priceUsd > 0 ? $priceUsd : null;
                    $selectionData['selected_unit_price_nio'] = $priceNio > 0 ? $priceNio : null;
                }

                SupplierBidSelection::updateOrCreate(
                    [
                        'quotation_request_id' => $request->id,
                        'quotation_request_item_id' => $quoteItem->id,
                    ],
                    $selectionData
                );

                $targetCurrency = strtoupper((string) ($request->currency ?: 'NIO'));
                $unitPrice = ($targetCurrency === 'USD' && $priceUsd > 0)
                    ? $priceUsd
                    : ($priceNio > 0 ? $priceNio : ($priceUsd > 0 ? round($priceUsd * 37.0, 2) : (float) $quoteItem->unit_price));

                $itemPayload = [
                    'selected_supplier_id' => $response->supplier_id,
                    'supplier_delivery_time' => $responseItem->delivery_time
                        ?: optional($responseItem->estimated_arrival_date)->format('Y-m-d'),
                    'supplier_response_notes' => $responseItem->internal_comment,
                    'supplier_selection_status' => 'selected',
                    'supplier_selection_exclusion_reason' => null,
                    'supplier_selection_excluded_by' => null,
                    'supplier_selection_excluded_at' => null,
                ];
                if ($unitPrice > 0) {
                    $itemPayload['unit_price'] = round($unitPrice, 2);
                    $itemPayload['line_total'] = round((float) $quoteItem->quantity * $unitPrice, 2);
                }

                $quoteItem->forceFill($itemPayload)->save();

                $this->exchangeRateService->syncSupplierCostInNio(
                    $quoteItem,
                    $responseItem->unit_cost,
                    $responseItem->currency ?: $response->currency,
                );

                $selected++;
            }

            if ($missingItems !== []) {
                $visibleItems = array_slice($missingItems, 0, 5);
                $remaining = count($missingItems) - count($visibleItems);
                $suffix = $remaining > 0 ? " y {$remaining} partida(s) más" : '';

                throw ValidationException::withMessages([
                    'selection' => 'No se puede preparar la oferta porque faltan respuestas válidas con cantidad y costo en: '
                        .implode(', ', $visibleItems).$suffix.'.',
                ]);
            }

            $this->activity->logGlobal(
                'supplier_bid.base_selection_created',
                "Se asignaron automáticamente {$selected} ofertas base para {$request->reference}. Compras podrá cambiar el proveedor final.",
                ['quote_id' => $request->id, 'selected_items' => $selected],
            );

            return $selected;
        });
    }

    public function removeSelection(QuotationRequestItem $item, User $user): void
    {
        DB::transaction(function () use ($item, $user) {
            $item->loadMissing('quotationRequest');
            $selection = SupplierBidSelection::where('quotation_request_item_id', $item->id)->first();
            $selection?->delete();

            $item->forceFill([
                'unit_cost' => null,
                'selected_supplier_id' => null,
                'selected_supplier_bid_item_id' => null,
                'supplier_delivery_time' => null,
                'supplier_response_notes' => null,
                'is_approved' => false,
                'supplier_selection_status' => null,
                'supplier_selection_exclusion_reason' => null,
                'supplier_selection_excluded_by' => null,
                'supplier_selection_excluded_at' => null,
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($item->quotationRequest);
            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.selection_removed',
                "Seleccion removida para partida {$this->activity->lineLabel($item)}",
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'quotation_request_items',
                $item->id,
                null,
                null,
                $user
            );

            $this->activity->logGlobal('supplier_bid.selection_removed', "Seleccion removida para partida {$this->activity->lineLabel($item)}", [
                'quote_id' => $item->quotation_request_id,
                'quote_item_id' => $item->id,
            ]);
        });
    }

    public function excludeItem(QuotationRequestItem $item, string $reason, User $user): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['selection' => 'Indica un motivo para excluir la partida.']);
        }

        DB::transaction(function () use ($item, $reason, $user) {
            $item->loadMissing('quotationRequest');
            SupplierBidSelection::where('quotation_request_item_id', $item->id)->delete();

            $item->forceFill([
                'unit_cost' => null,
                'selected_supplier_id' => null,
                'selected_supplier_bid_item_id' => null,
                'supplier_delivery_time' => null,
                'supplier_response_notes' => null,
                'is_approved' => false,
                'supplier_selection_status' => 'excluded',
                'supplier_selection_exclusion_reason' => $reason,
                'supplier_selection_excluded_by' => $user->id,
                'supplier_selection_excluded_at' => now(),
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($item->quotationRequest);
            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.item_excluded',
                "Partida {$this->activity->lineLabel($item)} excluida: {$reason}",
                SalesWorkflowService::PHASE_SUPPLIER_BID,
                'quotation_request_items',
                $item->id,
                null,
                null,
                $user
            );
        });
    }

    public function useAllItemsFromSupplier(SupplierBidResponse $response, User $user): int
    {
        $response->loadMissing('items');
        $count = 0;

        foreach ($response->items as $item) {
            if ($item->is_selectable) {
                $this->selectItem($item, $user);
                $count++;
            }
        }

        if ($count === 0) {
            throw ValidationException::withMessages(['selection' => 'Este proveedor no tiene partidas cotizadas seleccionables.']);
        }

        return $count;
    }

    public function sendSelectionsToClientOffer(QuotationRequest $request, User $user): QuotationRequest
    {
        return DB::transaction(function () use ($request, $user) {
            $request->refresh();
            $selections = $this->selectionValidator->execute($request);

            $request->items()->update(['is_approved' => false]);
            foreach ($selections as $selection) {
                $responseItem = $selection->responseItem;
                $priceUsd = (float) ($selection->selected_unit_price ?? $responseItem?->preliquidated_unit_price ?? 0);
                $priceNio = (float) ($selection->selected_unit_price_nio ?? $responseItem?->preliquidated_unit_price_nio ?? 0);
                $targetCurrency = strtoupper((string) ($request->currency ?: 'NIO'));
                $qItem = $selection->quotationItem;
                $unitPrice = ($targetCurrency === 'USD' && $priceUsd > 0)
                    ? $priceUsd
                    : ($priceNio > 0 ? $priceNio : ($priceUsd > 0 ? round($priceUsd * 37.0, 2) : (float) $qItem?->unit_price));

                $updateData = [
                    'unit_cost' => $selection->selected_unit_cost,
                    'selected_supplier_id' => $selection->selected_supplier_id,
                    'supplier_delivery_time' => $selection->delivery_time ?: optional($selection->estimated_arrival_date)->format('Y-m-d'),
                    'is_approved' => true,
                ];
                if ($unitPrice > 0) {
                    $updateData['unit_price'] = round($unitPrice, 2);
                    $updateData['line_total'] = round((float) ($qItem?->quantity ?? 1) * $unitPrice, 2);
                }

                $selection->quotationItem()->update($updateData);
            }

            $oldStatus = $request->status;
            $request->forceFill([
                'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
                'tax_percent' => 15,
            ])->save();

            app(ExchangeRateService::class)->applyToClientOffer($request->fresh(['items.supplierBidSelection.responseItem.response.supplier']));

            $tracker = $this->trackerService->trackerForQuotation($request);
            $tracker->update([
                'operational_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
                'operational_status' => $request->status,
            ]);

            $this->trackerService->recordActivity(
                $tracker,
                'supplier_bid.ready_for_client_offer',
                "Licitacion {$request->reference} enviada a Oferta Cliente con {$selections->count()} partidas",
                SalesWorkflowService::PHASE_CLIENT_OFFER,
                'quotation_requests',
                $request->id,
                $oldStatus,
                $request->status,
                $user
            );

            $this->activity->logGlobal('supplier_bid.ready_for_client_offer', "Licitacion enviada a Oferta Cliente {$request->reference}", [
                'quote_id' => $request->id,
                'selected_items' => $selections->count(),
            ]);

            return $request->fresh(['items', 'supplierBidSelections']);
        });
    }
}
