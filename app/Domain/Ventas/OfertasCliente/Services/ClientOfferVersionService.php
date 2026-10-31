<?php

namespace App\Domain\Ventas\OfertasCliente\Services;

use App\Models\ActivityLog;
use App\Models\ClientOfferVersion;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\Documents\ClientOfferDocumentMapper;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;

class ClientOfferVersionService
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly ClientOfferDocumentMapper $documentMapper
    ) {}

    public function createVersion(QuotationRequest $request, User $user, ?string $reason = null, ?string $pdfPath = null, string $status = 'generated'): ClientOfferVersion
    {
        return DB::transaction(function () use ($request, $user, $reason, $pdfPath, $status) {
            // The quotation is the aggregate root and serializes version
            // allocation only for this offer.
            $request = QuotationRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();
            $request->load('items.supplierBidSelection.responseItem.response.supplier', 'items.product');
            $offerItems = $request->items
                ->filter(fn ($item) => (bool) $item->is_approved || (float) $item->line_total > 0)
                ->values();
            $request->setRelation('items', $offerItems);

            $versionNumber = ((int) $request->offerVersions()->max('version_number')) + 1;
            $mappedItems = $request->items
                ->mapWithKeys(fn ($item) => [$item->id => $this->documentMapper->mapItem($item)]);
            $subtotal = (float) $mappedItems->sum(fn (array $item) => (float) $item['line_total']);
            $taxPercent = (float) ($request->tax_percent ?? 15);
            $tax = round($subtotal * ($taxPercent / 100), 2);
            $total = round($subtotal + $tax, 2);
            $snapshot = [
                'items' => $mappedItems->values()->all(),
                'quote' => [
                    'id' => $request->id,
                    'reference' => $request->reference,
                    'customer' => $request->quote_to_name,
                    'status' => $request->status,
                    'tax_percent' => $taxPercent,
                ],
            ];

            $version = ClientOfferVersion::create([
                'quotation_request_id' => $request->id,
                'company_issuer' => $request->company_issuer ?: 'quantum',
                'version_number' => $versionNumber,
                'reference' => $request->reference.'-V'.$versionNumber,
                'status' => $status,
                'currency' => 'NIO',
                'exchange_rate' => $request->exchange_rate,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'payment_terms' => $request->payment_terms,
                'valid_until' => now()->addDays(15)->toDateString(),
                'pdf_path' => $pdfPath,
                'reason' => $reason,
                'snapshot_json' => $snapshot,
                'change_reason' => $reason,
                'created_by' => $user->id,
            ]);

            foreach ($request->items as $item) {
                $mappedItem = $mappedItems->get($item->id);

                $version->items()->create([
                    'quotation_request_item_id' => $item->id,
                    'part_number' => $mappedItem['part_number'],
                    'brand' => $mappedItem['brand'] === '-' ? null : $mappedItem['brand'],
                    'description' => $mappedItem['part_name'],
                    'quantity' => $mappedItem['quantity'],
                    'unit_cost' => $item->unit_cost,
                    'unit_price' => $mappedItem['unit_price'],
                    'line_total' => $mappedItem['line_total'],
                ]);
            }

            $tracker = $this->trackerService->trackerForQuotation($request);
            $this->trackerService->addReference($tracker, 'client_offer_version', $version->reference, 'client_offer_versions', $version->id);
            $this->trackerService->recordActivity(
                $tracker,
                'client_offer.version_created',
                "Version de oferta cliente creada: {$version->reference}",
                SalesWorkflowService::PHASE_CLIENT_OFFER,
                'client_offer_versions',
                $version->id,
                null,
                $status,
                $user
            );

            ActivityLog::log('ventas', 'client_offer.version_created', "Version {$version->reference} creada para {$request->reference}", [
                'quote_id' => $request->id,
                'version_id' => $version->id,
            ]);

            return $version->fresh('items');
        }, (int) config('concurrency.transaction_attempts', 3));
    }
}
