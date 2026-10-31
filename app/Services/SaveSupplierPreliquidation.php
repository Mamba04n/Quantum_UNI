<?php

namespace App\Services;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseTracker;
use App\Models\{ActivityLog, Entity, QuotationRequest, SupplierBidResponse, SupplierBidResponseItem, SupplierRfqDocument, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveSupplierPreliquidation
{
    public static function revision(SupplierBidResponse $response): string
    {
        return hash('sha256', json_encode([$response->getAttributes(), $response->items()->orderBy('id')->get()->toArray()]));
    }

    public function save(QuotationRequest $quote, Entity $supplier, ?int $responseId, array $settings, array $rows, array $header, User $user, ?string $revision): array
    {
        \Illuminate\Support\Facades\Validator::make(['rows' => $rows, 'settings' => $settings], [
            'rows' => 'present|array|max:500', 'rows.*.quoted_part_number' => 'required|string|max:255',
            'rows.*.response_item_id' => 'nullable|integer|distinct',
            'rows.*.quoted_description' => 'required|string|max:1000', 'rows.*.quoted_quantity' => 'required|numeric|gt:0|max:999999999|decimal:0,2',
            'rows.*.unit_cost' => 'required|numeric|min:0|max:999999999|decimal:0,2', 'rows.*.weight_lb' => 'required|numeric|min:0|max:999999999',
            'settings.exchange' => 'required|numeric|gt:0|max:1000000', 'settings.margin' => 'required|numeric|min:0|max:99.99',
        ])->validate();
        return DB::transaction(function () use ($quote, $supplier, $responseId, $settings, $rows, $header, $user, $revision) {
            $quote = QuotationRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if (!in_array($quote->status, [QuotationRequest::STATUS_RFQ_GENERATED, QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, QuotationRequest::STATUS_SUPPLIER_SELECTED])) {
                throw ValidationException::withMessages(['preliquidation' => 'La fase actual no permite editar esta preliquidación.']);
            }
            $rfq = SupplierRfqDocument::where('quotation_request_id', $quote->id)->where('supplier_id', $supplier->id)->firstOrFail();
            if (!in_array($rfq->status, [SupplierRfqDocument::STATUS_GENERATED, SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER, SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED, SupplierRfqDocument::STATUS_NO_RESPONSE], true)) {
                throw ValidationException::withMessages(['preliquidation' => 'El RFQ de este proveedor todavía no permite registrar respuesta.']);
            }
            $repository = app(SupplierBidResponseRepository::class);
            $response = $responseId ? SupplierBidResponse::where('quotation_request_id', $quote->id)->where('supplier_id', $supplier->id)->lockForUpdate()->findOrFail($responseId) : null;
            if (($response && !hash_equals(self::revision($response), $revision ?? '')) || (!$response && SupplierBidResponse::where('quotation_request_id', $quote->id)->where('supplier_id', $supplier->id)->exists())) {
                throw ValidationException::withMessages(['preliquidation' => 'La respuesta cambió desde que la abriste. Recarga antes de guardar para no sobrescribir cambios.']);
            }
            if ($response && $response->items()->whereHas('selection')->exists()) {
                if (in_array($quote->status, [
                    QuotationRequest::STATUS_RFQ_GENERATED,
                    QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                    QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                    QuotationRequest::STATUS_PENDING_BID,
                    QuotationRequest::STATUS_READY_FOR_BID,
                ], true)) {
                    $itemIds = $response->items()->pluck('id');
                    \App\Models\SupplierBidSelection::whereIn('supplier_bid_response_item_id', $itemIds)->delete();
                } else {
                    throw ValidationException::withMessages(['preliquidation' => 'Esta respuesta ya tiene partidas seleccionadas para oferta.']);
                }
            }
            $existing = $response ? $response->items()->get()->keyBy('id') : collect();
            $before = ['settings' => $response?->preliquidation, 'items' => $existing->toArray(), 'notes' => $response?->notes];
            if (!$response) {
                $response = SupplierBidResponse::create(['quotation_request_id' => $quote->id, 'supplier_id' => $supplier->id,
                    'supplier_bid_id' => $repository->bidForSupplier($quote, $supplier, $user)?->id,
                    'response_code' => $repository->nextResponseCode($quote, $supplier), 'created_by' => $user->id, 'status' => 'received']);
            }
            $sourceIds = $quote->items()->pluck('id')->all();
            $inserts = $updates = $keys = [];
            foreach ($rows as $key => $row) {
                $id = $row['response_item_id'] ?? null;
                if ($id && !$existing->has($id)) abort(403);
                $sourceId = (array_key_exists('quotation_request_item_id', $row) ? $row['quotation_request_item_id'] : (ctype_digit((string) $key) ? (int) $key : null)) ?: null;
                if ($sourceId && !in_array((int) $sourceId, $sourceIds)) abort(403);
                $uuid = $id ? ($existing[$id]->preliquidation_key ?: (string) Str::uuid()) : (string) Str::uuid();
                $keys[$key] = $uuid;
                $data = ['supplier_bid_response_id' => $response->id, 'preliquidation_key' => $uuid,
                    'quotation_request_item_id' => $sourceId, 'customer_request_item_id' => $sourceId,
                    'quoted_part_number' => $row['quoted_part_number'], 'quoted_description' => $row['quoted_description'],
                    'quoted_quantity' => $row['quoted_quantity'], 'unit_cost' => $row['unit_cost'], 'currency' => 'USD',
                    'item_status' => $row['item_status'] ?? 'quoted', 'warranty' => $row['warranty'] ?? '',
                    'delivery_time' => $row['delivery_time'] ?? '', 'internal_comment' => $row['notes'] ?? '',
                    'created_at' => $id ? $existing[$id]->created_at : now(), 'updated_at' => now()];
                if ($id) { $data['id'] = $id; $updates[] = $data; } else { $inserts[] = $data; }
            }
            foreach (array_chunk($inserts, 100) as $chunk) SupplierBidResponseItem::insert($chunk);
            foreach (array_chunk($updates, 100) as $chunk) SupplierBidResponseItem::upsert($chunk, ['id'], ['preliquidation_key', 'quotation_request_item_id', 'customer_request_item_id', 'quoted_part_number', 'quoted_description', 'quoted_quantity', 'unit_cost', 'currency', 'item_status', 'warranty', 'delivery_time', 'internal_comment', 'updated_at']);
            $saved = $response->items()->whereIn('preliquidation_key', array_values($keys))->get()->keyBy('preliquidation_key');
            $response->items()->whereNotIn('id', $saved->pluck('id'))->delete();
            $weights = $assigned = $mapping = [];
            $calculator = app(PreliquidationCalculator::class);
            $calcResult = $calculator->calculate($settings, $rows);
            $hasPreliqPriceCols = \Illuminate\Support\Facades\Schema::hasColumn('supplier_bid_response_items', 'preliquidated_unit_price');

            foreach ($keys as $key => $uuid) {
                $id = $saved[$uuid]->id;
                $mapping[$key] = $id;
                $weights[$id] = (float) ($rows[$key]['weight_lb'] ?? 0);
                $assigned[$id] = ['usd' => $rows[$key]['assigned_usd'] ?? null, 'nio' => $rows[$key]['assigned_nio'] ?? null];

                if ($hasPreliqPriceCols) {
                    $rowCalc = $calcResult['rows'][$key] ?? [];
                    $assignedUsd = filled($rows[$key]['assigned_usd'] ?? null) && (float) $rows[$key]['assigned_usd'] > 0
                        ? (float) $rows[$key]['assigned_usd']
                        : null;
                    $assignedNio = filled($rows[$key]['assigned_nio'] ?? null) && (float) $rows[$key]['assigned_nio'] > 0
                        ? (float) $rows[$key]['assigned_nio']
                        : null;

                    $finalUsd = $assignedUsd ?? (float) ($rowCalc['price'] ?? 0);
                    $finalNio = $assignedNio ?? (float) ($rowCalc['price_nio'] ?? ($finalUsd * (float) ($settings['exchange'] ?? 37)));
                    $ddp = (float) ($rowCalc['ddp'] ?? 0);
                    $cif = (float) ($rowCalc['cif'] ?? 0);

                    SupplierBidResponseItem::where('id', $id)->update([
                        'preliquidated_unit_price' => round($finalUsd, 2),
                        'preliquidated_unit_price_nio' => round($finalNio, 2),
                        'ddp_unit_cost' => round($ddp, 2),
                        'cif_unit_cost' => round($cif, 2),
                        'margin_percentage' => (float) ($settings['margin'] ?? 0),
                    ]);
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('supplier_bid_selections')) {
                foreach ($mapping as $key => $responseItemId) {
                    $selection = \App\Models\SupplierBidSelection::where('supplier_bid_response_item_id', $responseItemId)->first();
                    if ($selection && $selection->quotationItem) {
                        $rowCalc = $calcResult['rows'][$key] ?? [];
                        $assignedUsd = filled($rows[$key]['assigned_usd'] ?? null) && (float) $rows[$key]['assigned_usd'] > 0
                            ? (float) $rows[$key]['assigned_usd']
                            : null;
                        $assignedNio = filled($rows[$key]['assigned_nio'] ?? null) && (float) $rows[$key]['assigned_nio'] > 0
                            ? (float) $rows[$key]['assigned_nio']
                            : null;
                        $finalUsd = $assignedUsd ?? (float) ($rowCalc['price'] ?? 0);
                        $finalNio = $assignedNio ?? (float) ($rowCalc['price_nio'] ?? ($finalUsd * (float) ($settings['exchange'] ?? 37)));

                        if (\Illuminate\Support\Facades\Schema::hasColumn('supplier_bid_selections', 'selected_unit_price')) {
                            $selection->update([
                                'selected_unit_cost' => $rows[$key]['unit_cost'],
                                'selected_unit_price' => round($finalUsd, 2),
                                'selected_unit_price_nio' => round($finalNio, 2),
                            ]);
                        }

                        $qItem = $selection->quotationItem;
                        $targetCurrency = strtoupper((string) ($quote->currency ?: 'NIO'));
                        $unitPrice = ($targetCurrency === 'USD' && $finalUsd > 0)
                            ? $finalUsd
                            : ($finalNio > 0 ? $finalNio : ($finalUsd > 0 ? round($finalUsd * 37.0, 2) : (float) $qItem->unit_price));

                        if ($unitPrice > 0) {
                            $qItem->forceFill([
                                'unit_price' => round($unitPrice, 2),
                                'line_total' => round((float) $qItem->quantity * $unitPrice, 2),
                                'unit_cost' => $rows[$key]['unit_cost'],
                            ])->save();
                        }
                    }
                }
            }

            // Store inputs and calculated results
            $response->fill($header + ['currency' => 'USD'])->forceFill([
                'preliquidation' => [
                    'settings' => $settings,
                    'weights' => $weights,
                    'assigned' => $assigned,
                    'calculated' => $calcResult['rows'] ?? [],
                    'totals' => $calcResult,
                ],
            ])->save();
            $rfq->update(['status' => SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED]);
            $tracker = app(SupplierBidResponseTracker::class);
            $tracker->markRequestPendingResponse($quote);
            if ($tracking = $tracker->trackerFor($quote)) {
                $tracker->syncTrackerToSupplier($tracking, $quote, $supplier);
                $tracker->addResponseReference($tracking, $response, $supplier);
            }
            ActivityLog::log('sales', 'preliquidation.saved', 'Preliquidación guardada por proveedor',
                ['quote_id' => $quote->id, 'supplier_id' => $supplier->id, 'response_id' => $response->id,
                    'before' => $before, 'after' => ['settings' => $response->preliquidation, 'items' => $response->items()->get()->toArray(), 'notes' => $response->notes]]);
            return [$response, $mapping];
        });
    }
}
