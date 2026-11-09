<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Models\Entity;
use App\Models\OrderTrackerAttachment;
use App\Models\SupplierBidResponse;
use App\Models\SupplierRfqDocument;
use App\Services\OrderTrackerService;
use App\Services\SupplierBidResponseService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait PersistsSupplierBidResponses
{
    public function saveAllSupplierResponseItems(SupplierBidResponseService $bidService): void
    {
        if (empty($this->preliquidation)) {
            $this->saveSupplierResponseDraft(array_merge(array_keys($this->supplierBidItems), array_keys($this->supplierBidExtraItems)), $bidService);
            return;
        }
        $this->validatePreliquidation();
        $quote = $this->selectedQuote();
        $rfq = $this->supplierRfqDocumentForSelectedSupplier();
        abort_unless($quote && $rfq && auth()->user(), 403);
        $supplier = $this->supplierForResponse($rfq);
        $this->validate([
            'supplierContactName' => 'nullable|string|max:255',
            'supplierNotes' => 'nullable|string|max:2000',
            'supplierPaymentTerms' => 'nullable|string|max:255',
            'supplierDeliveryTime' => 'nullable|string|max:255',
            'supplierWarranty' => 'nullable|string|max:255',
            'supplierValidUntil' => 'nullable|date',
            'supplierProformas' => 'array|max:10',
            'supplierProformas.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv|max:10240',
            'supplierItemProformas.*' => 'array|max:10',
            'supplierItemProformas.*.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv|max:10240',
        ]);
        $rows = $this->supplierBidItems + $this->supplierBidExtraItems;
        $trackerService = app(OrderTrackerService::class);
        $tracker = ($this->supplierProformas || $this->supplierItemProformas)
            ? $trackerService->trackerForQuotation($quote, false)
            : null;
        if (!$tracker && ($this->supplierProformas || $this->supplierItemProformas)) {
            throw ValidationException::withMessages(['preliquidation' => 'No existe seguimiento para adjuntar archivos.']);
        }
        $storedPaths = [];
        try {
            [$response, $mapping] = \Illuminate\Support\Facades\DB::transaction(function () use ($quote, $supplier, $rows, $trackerService, $tracker, &$storedPaths) {
                [$response, $mapping] = app(\App\Services\SaveSupplierPreliquidation::class)->save(
                    $quote, $supplier, $this->activeSupplierBidResponseId ?: $this->editingSupplierResponseId,
                    $this->preliquidation, $rows, [
                        'contact_name' => $this->supplierContactName, 'payment_terms' => $this->supplierPaymentTerms,
                        'general_delivery_time' => $this->supplierDeliveryTime, 'general_warranty' => $this->supplierWarranty,
                        'valid_until' => $this->supplierValidUntil ?: null, 'notes' => $this->supplierNotes,
                    ], auth()->user(), $this->preliquidationRevision);
                foreach ($mapping as $key => $id) {
                    foreach ((array) ($this->supplierItemProformas[$key] ?? []) as $file) {
                        $attachment = $trackerService->addAttachment($tracker, $file, 'supplier_bid', 'Evidencia de partida', null, 'supplier_bid_response_items', $id);
                        $storedPaths[] = $attachment->file_path;
                    }
                }
                foreach ($this->supplierProformas as $file) {
                    $attachment = $trackerService->addAttachment($tracker, $file, 'supplier_bid', 'Documento del proveedor', null, 'supplier_bid_responses', $response->id);
                    $storedPaths[] = $attachment->file_path;
                    $response->forceFill(['attachment_path' => $attachment->file_path, 'supplier_document_path' => $attachment->file_path])->save();
                }
                return [$response, $mapping];
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('private')->delete($path);
            throw $exception;
        }
        $this->supplierProformas = $this->supplierItemProformas = [];
        $this->loadSavedSupplierResponse($response->fresh());
        $this->advanceStageError = null;
        $this->dispatch('preliquidation-saved');
        $viaTransport = ($this->preliquidation['transport'] ?? 'air') === 'sea' ? 'Vía Marítima 🚢' : 'Vía Aérea ✈️';
        $this->dispatch('notify', ['type' => 'success', 'message' => "Preliquidación guardada ({$viaTransport}): ".count($mapping).' partidas.']);
    }

    public function saveSupplierResponseDraft(array $itemIds, SupplierBidResponseService $bidService): void
    {
        $quote = $this->selectedQuote();
        $rfqDocument = $this->supplierRfqDocumentForSelectedSupplier();
        if (! $quote || ! $rfqDocument || ! in_array($rfqDocument->status, [SupplierRfqDocument::STATUS_GENERATED, SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER, SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED, SupplierRfqDocument::STATUS_NO_RESPONSE], true)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona un proveedor con RFQ enviado antes de guardar.']);

            return;
        }

        $allowedItemIds = collect($this->rfqItemIdsForResponse($quote, $rfqDocument))->map(fn ($id) => (int) $id);
        $requestedItemIds = collect($itemIds)
            ->map(fn ($id) => (string) $id)
            ->filter(function (string $key) use ($allowedItemIds): bool {
                if (isset($this->supplierBidExtraItems[$key])) {
                    $extra = $this->supplierBidExtraItems[$key];
                    if (! empty($extra['is_standalone'])) {
                        return true;
                    }

                    return $allowedItemIds->contains((int) ($extra['quotation_request_item_id'] ?? 0));
                }

                return ctype_digit($key) && $allowedItemIds->contains((int) $key);
            })
            ->unique()
            ->values();

        if ($requestedItemIds->isEmpty() && $this->supplierBidDeletedItemIds === []) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'No hay cambios pendientes por guardar.']);

            return;
        }

        foreach ($requestedItemIds as $itemKey) {
            $this->resetErrorBag(isset($this->supplierBidExtraItems[$itemKey]) ? "supplierBidExtraItems.{$itemKey}" : "supplierBidItems.{$itemKey}");
        }

        try {
            $supplier = $this->supplierForResponse($rfqDocument);
        } catch (ValidationException $exception) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($exception->errors())->flatten()->first()]);

            return;
        }

        $savedIds = [];
        $conflictIds = [];
        $errorIds = [];

        $responseId = $this->activeSupplierBidResponseId ?: $this->editingSupplierResponseId;
        $deletedCount = 0;
        if ($responseId && $this->supplierBidDeletedItemIds !== []) {
            $deletable = \App\Models\SupplierBidResponseItem::query()
                ->where('supplier_bid_response_id', $responseId)
                ->whereIn('id', array_unique(array_map('intval', $this->supplierBidDeletedItemIds)))
                ->whereDoesntHave('selection')
                ->get();
            foreach ($deletable as $deletedItem) {
                foreach ($deletedItem->attachments as $attachment) {
                    Storage::disk('private')->delete($attachment->file_path);
                    $attachment->delete();
                }
                $deletedItem->delete();
                $deletedCount++;
            }
            $this->supplierBidDeletedItemIds = [];
        }

        foreach ($requestedItemIds as $itemKey) {
            $isExtra = isset($this->supplierBidExtraItems[$itemKey]);
            $row = $isExtra ? $this->supplierBidExtraItems[$itemKey] : ($this->supplierBidItems[(int) $itemKey] ?? []);
            $itemId = $isExtra ? (int) ($row['quotation_request_item_id'] ?? 0) : (int) $itemKey;
            $item = $quote->items->firstWhere('id', $itemId);
            $errorPath = $isExtra ? "supplierBidExtraItems.{$itemKey}" : "supplierBidItems.{$itemId}";
            if (! $item) {
                continue;
            }

            if (! $isExtra && empty($this->preliquidation)) {
                $row['quoted_quantity'] = filled($row['quoted_quantity'] ?? null) ? (float) $row['quoted_quantity'] : (float) $item->quantity;
                $row['quantity'] = $row['quoted_quantity'];
            }

            $validator = Validator::make($row, [
                'unit_cost' => ['nullable', 'numeric', 'min:0'],
                'quoted_quantity' => ['required', 'numeric', 'gt:0'],
                'item_status' => ['nullable', 'in:quoted,alternative,not_quoted'],
                'quoted_part_number' => ['nullable', 'string', 'max:255'],
                'quoted_description' => ['nullable', 'string', 'max:1000'],
                'part_model' => ['nullable', 'string', 'max:255'],
                'serial_number' => ['nullable', 'string', 'max:255'],
                'brand' => ['nullable', 'string', 'max:255'],
                'delivery_time' => ['nullable', 'string', 'max:255'],
                'warranty' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($validator->fails()) {
                $errorIds[] = $itemKey;
                foreach ($validator->errors()->all() as $message) {
                    $this->addError($errorPath, $message);
                }
                continue;
            }

            $files = (array) ($this->supplierItemProformas[$itemKey] ?? []);
            $fileValidator = Validator::make(['files' => $files], [
                'files' => ['array', 'max:10'],
                'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv', 'max:10240'],
            ]);
            if ($fileValidator->fails()) {
                $errorIds[] = $itemKey;
                $this->addError($errorPath, $fileValidator->errors()->first());
                continue;
            }

            $savedItem = $responseId
                ? \App\Models\SupplierBidResponseItem::query()
                    ->where('supplier_bid_response_id', $responseId)
                    ->when(filled($row['response_item_id'] ?? null), fn ($query) => $query->whereKey((int) $row['response_item_id']))
                    ->when(blank($row['response_item_id'] ?? null), fn ($query) => $query->where('quotation_request_item_id', $itemId))
                    ->first()
                : null;
            $knownVersion = $this->supplierBidItemVersions[$itemKey] ?? null;

            if ($savedItem && $knownVersion && $savedItem->updated_at?->toISOString() !== $knownVersion) {
                $conflictIds[] = $itemKey;
                $this->addError($errorPath, 'Esta línea fue modificada por otro usuario. Actualiza la cotización antes de sobrescribirla.');
                continue;
            }

            try {
                $row['quantity'] = (float) ($row['quoted_quantity'] ?? $item->quantity);
                $row['quoted_quantity'] = (float) ($row['quoted_quantity'] ?? $item->quantity);
                $row['not_quoted'] = ($row['item_status'] ?? 'quoted') === 'not_quoted';

                $responseItem = $bidService->saveResponseItem($quote, $item, [
                    'response_id' => $responseId,
                    'supplier_id' => $supplier->id,
                    'contact_name' => $this->supplierContactName,
                    'currency' => $this->supplierCurrency,
                    'payment_terms' => $this->supplierPaymentTerms,
                    'general_delivery_time' => $this->supplierDeliveryTime,
                    'general_warranty' => $this->supplierWarranty ?: 'Sin garantía',
                    'valid_until' => $this->supplierValidUntil ?: null,
                    'notes' => $this->supplierNotes,
                    'item' => $row,
                    'allow_incomplete_item' => true,
                    'response_item_id' => $row['response_item_id'] ?? null,
                    'create_response_line' => $isExtra && blank($row['response_item_id'] ?? null),
                ], auth()->user());

                $this->activeSupplierBidResponseId = (int) $responseItem->supplier_bid_response_id;
                $this->editingSupplierResponseId = (int) $responseItem->supplier_bid_response_id;
                $this->supplierBidItemVersions[$itemKey] = $responseItem->updated_at?->toISOString();
                if ($isExtra) {
                    $this->supplierBidExtraItems[$itemKey]['response_item_id'] = $responseItem->id;
                } else {
                    $this->supplierBidItems[$itemId]['response_item_id'] = $responseItem->id;
                }

                if (! empty($this->supplierItemProformas[$itemKey])) {
                    $tracker = app(OrderTrackerService::class)->trackerForQuotation($quote);
                    if ($tracker) {
                        foreach ((array) $this->supplierItemProformas[$itemKey] as $itemFile) {
                            if (! $itemFile) {
                                continue;
                            }

                            app(OrderTrackerService::class)->addAttachment(
                                $tracker,
                                $itemFile,
                                'supplier_bid',
                                "Prueba ítem {$item->part_number} - {$supplier->name}",
                                null,
                                'supplier_bid_response_items',
                                $responseItem->id,
                            );
                        }
                    }
                    unset($this->supplierItemProformas[$itemKey]);
                }

                $savedIds[] = $itemKey;
            } catch (ValidationException $exception) {
                $errorIds[] = $itemKey;
                $this->addError($errorPath, collect($exception->errors())->flatten()->first());
            } catch (\Throwable $exception) {
                report($exception);
                $errorIds[] = $itemKey;
                $this->addError($errorPath, 'No se pudo guardar esta línea. Intenta nuevamente.');
            }
        }

        if ($deletedCount > 0) {
            $savedIds[] = '__deleted__';
        }

        if ($savedIds !== []) {
            $response = SupplierBidResponse::where('quotation_request_id', $quote->id)
                ->find($this->activeSupplierBidResponseId);
            if ($response && $errorIds === [] && $conflictIds === [] && ! empty($this->preliquidation)) {
                $weights = [];
                $assigned = [];
                foreach ($this->supplierBidItems + $this->supplierBidExtraItems as $savedRow) {
                    if (! empty($savedRow['response_item_id'])) {
                        $weights[$savedRow['response_item_id']] = (float) ($savedRow['weight_lb'] ?? 0);
                        if (isset($savedRow['assigned_usd']) || isset($savedRow['assigned_nio'])) {
                            $assigned[$savedRow['response_item_id']] = [
                                'usd' => $savedRow['assigned_usd'] ?? null,
                                'nio' => $savedRow['assigned_nio'] ?? null,
                            ];
                        }
                    }
                }
                $response->update(['preliquidation' => [
                    'settings' => $this->preliquidation,
                    'weights' => $weights,
                    'assigned' => $assigned,
                    'totals' => $this->preliquidationTotals(),
                ]]);
            }
            $rfqDocument->forceFill([
                'supplier_id' => $supplier->id,
                'status' => SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ])->save();
            $this->selectedSupplierId = (string) $supplier->id;
        }

        $summaryRows = collect($this->supplierBidItems)
            ->filter(fn (array $row, $itemId) => $allowedItemIds->contains((int) $itemId))
            ->concat(collect($this->supplierBidExtraItems)->filter(
                fn (array $row) => $allowedItemIds->contains((int) ($row['quotation_request_item_id'] ?? 0))
            ));
        $complete = $summaryRows->filter(function (array $row): bool {
            if (($row['item_status'] ?? 'quoted') === 'not_quoted') {
                return false;
            }

            return (float) ($row['unit_cost'] ?? 0) > 0
                && filled($row['warranty'] ?? null)
                && filled($row['delivery_time'] ?? null);
        })->count();
        $unavailable = $summaryRows->where('item_status', 'not_quoted')->count();
        $pending = max(0, $summaryRows->count() - $complete - $unavailable);

        $this->dispatch('supplier-bid-draft-saved', savedIds: $savedIds, conflictIds: $conflictIds, errorIds: $errorIds);
        $this->dispatch('notify', [
            'type' => ($conflictIds !== [] || $errorIds !== []) ? 'warning' : 'success',
            'message' => count($savedIds)." guardadas · {$complete} completas · {$pending} pendientes · {$unavailable} sin respuesta".
                (($conflictIds !== [] || $errorIds !== []) ? ' · '.(count($conflictIds) + count($errorIds)).' requieren revisión' : ''),
        ]);
    }

    public function saveSupplierResponse(SupplierBidResponseService $bidService, bool $addAnother = false): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $this->validate([
                'selectedSupplierId' => ['nullable', 'integer'],
                'responseSupplierName' => ['nullable', 'string', 'max:255'],
                'supplierContactName' => ['nullable', 'string', 'max:255'],
                'supplierCurrency' => ['required', 'exists:currencies,code'],
                'supplierPaymentTerms' => ['nullable', 'string', 'max:255'],
                'supplierDeliveryTime' => ['nullable', 'string', 'max:255'],
                'supplierWarranty' => ['required', 'string', 'max:255'],
                'supplierValidUntil' => ['nullable', 'date'],
                'supplierNotes' => ['nullable', 'string', 'max:2000'],
                'supplierProformas.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv', 'max:10240'],
                'supplierBidItems.*.quantity' => ['required', 'numeric', 'gt:0'],
                'supplierBidItems.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
                'supplierBidItems.*.not_quoted' => ['nullable', 'boolean'],
                'supplierBidItems.*.item_status' => ['nullable', 'in:quoted,alternative,not_quoted'],
                'supplierBidItems.*.quoted_part_number' => ['nullable', 'string', 'max:255'],
                'supplierBidItems.*.quoted_description' => ['nullable', 'string', 'max:1000'],
                'supplierBidItems.*.estimated_arrival_date' => ['nullable', 'date'],
                'supplierBidItems.*.part_model' => ['nullable', 'string', 'max:255'],
                'supplierBidItems.*.serial_number' => ['nullable', 'string', 'max:255'],
                'supplierBidItems.*.brand' => ['nullable', 'string', 'max:255'],
                'supplierBidItems.*.delivery_time' => ['nullable', 'string', 'max:255'],
                'supplierBidItems.*.warranty' => ['required', 'string', 'max:255'],
                'supplierBidItems.*.notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $rfqDocument = $this->supplierRfqDocumentForSelectedSupplier();
            if (! $rfqDocument || ! in_array($rfqDocument->status, [SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER, SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED, SupplierRfqDocument::STATUS_NO_RESPONSE], true)) {
                throw ValidationException::withMessages(['selectedSupplierId' => 'Primero debe existir un RFQ con PDF generado y marcado como enviado para este proveedor.']);
            }

            $supplier = $this->supplierForResponse($rfqDocument);
            $responseItemIds = $this->rfqItemIdsForResponse($quote, $rfqDocument);

            $response = $bidService->saveResponse($quote, [
                'response_id' => $this->editingSupplierResponseId,
                'supplier_id' => $supplier->id,
                'contact_name' => $this->supplierContactName,
                'currency' => $this->supplierCurrency,
                'payment_terms' => $this->supplierPaymentTerms,
                'general_delivery_time' => $this->supplierDeliveryTime,
                'general_warranty' => $this->supplierWarranty ?: 'Sin garantía',
                'valid_until' => $this->supplierValidUntil ?: null,
                'notes' => $this->supplierNotes,
                'items' => $this->supplierBidItems,
                'item_ids' => $responseItemIds,
            ], auth()->user(), $this->supplierProformas);

            $rfqDocument->forceFill([
                'supplier_id' => $supplier->id,
                'status' => SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ])->save();

            if ($addAnother) {
                $this->resetSupplierBidForm();
                $this->bidStep = 2;
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Cotización guardada. Puedes registrar otra respuesta.']);
            } else {
                $this->selectedSupplierId = (string) $supplier->id;
                $this->activeSupplierBidResponseId = (int) $response->id;
                $this->editingSupplierResponseId = (int) $response->id;
                $this->responseSupplierName = '';
                $this->supplierProformas = [];
                $this->resetValidation();
                $this->dispatch('notify', ['type' => 'success', 'message' => "Cotización de {$supplier->name} guardada ({$response->response_code})."]);
            }
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function saveAndAddAnotherSupplierResponse(SupplierBidResponseService $bidService): void
    {
        $this->saveSupplierResponse($bidService, true);
    }

    public function saveSupplierResponseItem(int $itemId, SupplierBidResponseService $bidService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $this->validate([
                'selectedSupplierId' => ['nullable', 'integer'],
                'responseSupplierName' => ['nullable', 'string', 'max:255'],
                'supplierContactName' => ['nullable', 'string', 'max:255'],
                'supplierCurrency' => ['required', 'exists:currencies,code'],
                "supplierBidItems.{$itemId}.unit_cost" => ['nullable', 'numeric', 'min:0'],
                "supplierBidItems.{$itemId}.item_status" => ['nullable', 'in:quoted,alternative,not_quoted'],
                "supplierBidItems.{$itemId}.quoted_part_number" => ['nullable', 'string', 'max:255'],
                "supplierBidItems.{$itemId}.quoted_description" => ['nullable', 'string', 'max:1000'],
                "supplierBidItems.{$itemId}.part_model" => ['nullable', 'string', 'max:255'],
                "supplierBidItems.{$itemId}.serial_number" => ['nullable', 'string', 'max:255'],
                "supplierBidItems.{$itemId}.brand" => ['nullable', 'string', 'max:255'],
                "supplierBidItems.{$itemId}.delivery_time" => ['nullable', 'string', 'max:255'],
                "supplierBidItems.{$itemId}.warranty" => ['required', 'string', 'max:255'],
                "supplierBidItems.{$itemId}.notes" => ['nullable', 'string', 'max:1000'],
                'supplierProformas.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv', 'max:10240'],
            ]);

            $rfqDocument = $this->supplierRfqDocumentForSelectedSupplier();
            if (! $rfqDocument || ! in_array($rfqDocument->status, [SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER, SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED, SupplierRfqDocument::STATUS_NO_RESPONSE], true)) {
                throw ValidationException::withMessages(['selectedSupplierId' => 'Primero debe existir un RFQ con PDF generado y marcado como enviado para este proveedor.']);
            }

            $item = $quote->items->firstWhere('id', $itemId);
            if (! $item) {
                throw ValidationException::withMessages(['supplierBidItems' => 'La partida seleccionada no existe en esta solicitud.']);
            }

            $allowedItemIds = $this->rfqItemIdsForResponse($quote, $rfqDocument);
            if (! in_array((int) $item->id, $allowedItemIds, true)) {
                throw ValidationException::withMessages(['supplierBidItems' => 'Esta partida no pertenece al RFQ seleccionado.']);
            }

            $supplier = $this->supplierForResponse($rfqDocument);
            $row = $this->supplierBidItems[$itemId] ?? [];
            $row['quoted_quantity'] = filled($row['quoted_quantity'] ?? null) ? (float) $row['quoted_quantity'] : (float) $item->quantity;
            $row['quantity'] = $row['quoted_quantity'];

            $responseItem = $bidService->saveResponseItem($quote, $item, [
                'response_id' => $this->activeSupplierBidResponseId,
                'supplier_id' => $supplier->id,
                'contact_name' => $this->supplierContactName,
                'currency' => $this->supplierCurrency,
                'payment_terms' => $this->supplierPaymentTerms,
                'general_delivery_time' => $this->supplierDeliveryTime,
                'general_warranty' => $this->supplierWarranty ?: 'Sin garantía',
                'valid_until' => $this->supplierValidUntil ?: null,
                'notes' => $this->supplierNotes,
                'item' => $row,
            ], auth()->user(), $this->supplierProformas);

            // Guardar archivos específicos de este ítem si se adjuntaron
            if (!empty($this->supplierItemProformas[$itemId])) {
                $tracker = app(OrderTrackerService::class)->trackerForQuotation($quote);
                if ($tracker) {
                    $itemFiles = is_array($this->supplierItemProformas[$itemId]) ? $this->supplierItemProformas[$itemId] : [$this->supplierItemProformas[$itemId]];
                    foreach ($itemFiles as $itemFile) {
                        if ($itemFile) {
                            app(OrderTrackerService::class)->addAttachment(
                                $tracker,
                                $itemFile,
                                'supplier_bid',
                                "Prueba ítem {$item->part_number} - {$supplier->name}",
                                null,
                                'supplier_bid_response_items',
                                $responseItem->id
                            );
                        }
                    }
                }
                unset($this->supplierItemProformas[$itemId]);
            }

            $rfqDocument->forceFill([
                'supplier_id' => $supplier->id,
                'status' => SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ])->save();

            $this->selectedSupplierId = (string) $supplier->id;
            $this->activeSupplierBidResponseId = (int) $responseItem->supplier_bid_response_id;
            $this->editingSupplierResponseId = (int) $responseItem->supplier_bid_response_id;
            $this->responseSupplierName = '';
            $this->supplierProformas = [];
            $this->resetValidation();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Partida guardada en la cotizacion del proveedor.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function removePendingSupplierProforma(int $index): void
    {
        if (! isset($this->supplierProformas[$index])) {
            return;
        }

        unset($this->supplierProformas[$index]);
        $this->supplierProformas = array_values($this->supplierProformas);
    }

    public function saveSupplierResponseDocuments(OrderTrackerService $trackerService, SupplierBidResponseRepository $responses): void
    {
        $quote = $this->selectedQuote();
        $rfqDocument = $this->supplierRfqDocumentForSelectedSupplier();
        if (! $quote || ! $rfqDocument?->supplier_id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona el proveedor al que pertenece esta respuesta antes de guardar archivos.']);

            return;
        }

        try {
            $this->validate([
                'supplierProformas' => ['required', 'array', 'min:1', 'max:10'],
                'supplierProformas.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv', 'max:10240'],
            ]);

            $supplier = $rfqDocument->supplier()->firstOrFail();
            $response = SupplierBidResponse::query()
                ->where('quotation_request_id', $quote->id)
                ->where('supplier_id', $supplier->id)
                ->whereDoesntHave('items.selection')
                ->latest('id')
                ->first();

            if (! $response) {
                $supplierBid = $responses->bidForSupplier($quote, $supplier, auth()->user());
                $response = SupplierBidResponse::create([
                    'quotation_request_id' => $quote->id,
                    'supplier_bid_id' => $supplierBid?->id,
                    'supplier_id' => $supplier->id,
                    'response_code' => $responses->nextResponseCode($quote, $supplier),
                    'currency' => $this->supplierCurrency ?: 'USD',
                    'general_warranty' => $this->supplierWarranty ?: 'Sin garantía',
                    'status' => 'received',
                    'created_by' => auth()->id(),
                ]);
            }

            $tracker = $trackerService->trackerForQuotation($quote);
            if (! $tracker) {
                throw ValidationException::withMessages(['supplierProformas' => 'No se encontró el seguimiento de esta solicitud para almacenar los archivos.']);
            }

            foreach ($this->supplierProformas as $file) {
                $attached = $trackerService->addAttachment(
                    $tracker,
                    $file,
                    'supplier_bid',
                    "Documento proveedor {$supplier->name}",
                    null,
                    'supplier_bid_responses',
                    $response->id,
                );

                $response->forceFill([
                    'attachment_path' => $attached->file_path,
                    'supplier_document_path' => $attached->file_path,
                ])->save();
            }

            $rfqDocument->forceFill(['status' => SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED])->save();
            $this->activeSupplierBidResponseId = $response->id;
            $this->editingSupplierResponseId = $response->id;
            $this->supplierProformas = [];
            $this->dispatch('notify', ['type' => 'success', 'message' => "Archivo(s) guardado(s) para {$supplier->name}."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function deleteSupplierResponseDocument(int $attachmentId): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        $attachment = OrderTrackerAttachment::query()
            ->whereKey($attachmentId)
            ->where('source_module', 'supplier_bid_responses')
            ->first();

        $response = $attachment
            ? SupplierBidResponse::query()
                ->where('quotation_request_id', $quote->id)
                ->whereKey($attachment->source_id)
                ->first()
            : null;

        if (! $attachment || ! $response) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El documento no pertenece a esta cotización de proveedor.']);

            return;
        }

        Storage::disk('private')->delete($attachment->file_path);
        $attachment->delete();

        $latest = $response->supplierDocumentAttachments()->latest('id')->first();
        $response->forceFill([
            'attachment_path' => $latest?->file_path,
            'supplier_document_path' => $latest?->file_path,
        ])->save();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Documento eliminado de la respuesta del proveedor.']);
    }

    public function removePendingSupplierItemProforma(int $itemId, int $index): void
    {
        if (isset($this->supplierItemProformas[$itemId][$index])) {
            unset($this->supplierItemProformas[$itemId][$index]);
            $this->supplierItemProformas[$itemId] = array_values($this->supplierItemProformas[$itemId]);
        }
    }

    public function deleteSupplierItemResponseDocument(int $attachmentId): void
    {
        $attachment = OrderTrackerAttachment::query()
            ->whereKey($attachmentId)
            ->whereIn('source_module', ['supplier_bid_response_items', 'supplier_bid_response_item'])
            ->first();

        if (! $attachment) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El documento de la partida no fue encontrado.']);

            return;
        }

        Storage::disk('private')->delete($attachment->file_path);
        $attachment->delete();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Prueba de la partida eliminada.']);
    }

    public function establishOpenRfqSupplier(): void
    {
        try {
            $this->validate([
                'responseSupplierName' => ['required', 'string', 'max:255'],
                'supplierContactName' => ['nullable', 'string', 'max:255'],
            ]);

            $rfqDocument = $this->supplierRfqDocumentForSelectedSupplier();
            if (! $rfqDocument || filled($rfqDocument->supplier_id)) {
                throw ValidationException::withMessages(['selectedSupplierId' => 'No hay un RFQ abierto para fijar proveedor.']);
            }

            $supplier = Entity::firstOrCreate(
                [
                    'type' => 'proveedor',
                    'name' => trim($this->responseSupplierName),
                ],
                [
                    'status' => 'activo',
                ]
            );

            if (filled($this->supplierContactName)) {
                $supplier->forceFill(['contact_name' => trim($this->supplierContactName)])->save();
            }

            if (SupplierRfqDocument::where('quotation_request_id', $rfqDocument->quotation_request_id)->where('supplier_id', $supplier->id)->exists()) {
                throw ValidationException::withMessages(['responseSupplierName' => 'Este proveedor ya está agregado. Selecciónalo en las pestañas para editar su preliquidación.']);
            }

            $rfqDocument->forceFill(['supplier_id' => $supplier->id])->save();

            $this->selectedSupplierId = (string) $supplier->id;
            $this->responseSupplierName = '';
            $this->supplierContactName = (string) ($supplier->contact_name ?? '');

            $this->resetErrorBag('responseSupplierName');
            $this->resetErrorBag('selectedSupplierId');
            $this->dispatch('rfq-supplier-established');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Proveedor fijado para este RFQ. Ya puedes registrar la cotizacion.']);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }
}
