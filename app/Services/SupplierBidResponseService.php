<?php

namespace App\Services;

use App\Domain\Ventas\LicitacionesProveedor\Actions\ManageSupplierBidResponse;
use App\Domain\Ventas\LicitacionesProveedor\Actions\ManageSupplierBidSelection;
use App\Domain\Ventas\LicitacionesProveedor\Actions\RecordSupplierBidResponse;
use App\Domain\Ventas\LicitacionesProveedor\Actions\RecordSupplierBidResponseItem;
use App\Domain\Ventas\LicitacionesProveedor\Queries\MeasureSupplierBidCoverage;
use App\Domain\Ventas\LicitacionesProveedor\Queries\ValidateSupplierSelectionsForOffer;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierBidResponseService
{
    public function __construct(
        private readonly RecordSupplierBidResponse $recordResponse,
        private readonly RecordSupplierBidResponseItem $recordResponseItem,
        private readonly ManageSupplierBidResponse $manageResponse,
        private readonly ManageSupplierBidSelection $manageSelection,
        private readonly ValidateSupplierSelectionsForOffer $selectionValidator,
        private readonly MeasureSupplierBidCoverage $coverage,
    ) {}

    public function saveResponse(QuotationRequest $request, array $data, User $user, array $attachments = []): SupplierBidResponse
    {
        return $this->recordResponse->execute($request, $data, $user, $attachments);
    }

    public function saveResponseItem(QuotationRequest $request, QuotationRequestItem $item, array $data, User $user, array $attachments = []): SupplierBidResponseItem
    {
        return $this->recordResponseItem->execute($request, $item, $data, $user, $attachments);
    }

    public function selectItem(SupplierBidResponseItem $responseItem, User $user, ?float $quantity = null): SupplierBidSelection
    {
        return $this->manageSelection->selectItem($responseItem, $user, $quantity);
    }

    public function selectLowestCostItemsForOffer(QuotationRequest $request, User $user): int
    {
        return $this->manageSelection->selectLowestCostItemsForOffer($request, $user);
    }

    public function deleteResponse(SupplierBidResponse $response, User $user): void
    {
        $this->manageResponse->delete($response, $user);
    }

    public function duplicateResponse(SupplierBidResponse $response, User $user): SupplierBidResponse
    {
        return $this->manageResponse->duplicate($response, $user);
    }

    public function removeSelection(QuotationRequestItem $item, User $user): void
    {
        $this->manageSelection->removeSelection($item, $user);
    }

    public function excludeItem(QuotationRequestItem $item, string $reason, User $user): void
    {
        $this->manageSelection->excludeItem($item, $reason, $user);
    }

    public function useAllItemsFromSupplier(SupplierBidResponse $response, User $user): int
    {
        return $this->manageSelection->useAllItemsFromSupplier($response, $user);
    }

    public function validateSelectionsBeforeOffer(QuotationRequest $request): Collection
    {
        return $this->selectionValidator->execute($request);
    }

    public function responseCoverage(QuotationRequest $request): array
    {
        return $this->coverage->execute($request);
    }

    public function assertHasValidResponseForSelection(QuotationRequest $request): void
    {
        $request->loadMissing(['items', 'supplierRfqDocuments.supplier', 'supplierBidResponses.items']);

        // Limpiar RFQs huérfanos o temporales sin proveedor asignado
        $request->supplierRfqDocuments
            ->filter(fn (SupplierRfqDocument $document) => blank($document->supplier_id))
            ->each(fn (SupplierRfqDocument $document) => $document->delete());

        $pendingDocuments = $request->supplierRfqDocuments
            ->filter(fn (SupplierRfqDocument $document) => $document->status === SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER && filled($document->supplier_id));

        if ($pendingDocuments->isNotEmpty()) {
            $suppliers = $pendingDocuments
                ->map(fn (SupplierRfqDocument $document) => $document->supplier?->name ?: 'Proveedor pendiente')
                ->unique()
                ->implode(', ');

            throw ValidationException::withMessages([
                'supplier_response' => "Falta cerrar la respuesta de: {$suppliers}. Registra todas sus partidas o márcalo como Sin respuesta.",
            ]);
        }

        $receivedDocuments = $request->supplierRfqDocuments
            ->filter(fn (SupplierRfqDocument $document) => $document->status === SupplierRfqDocument::STATUS_SUPPLIER_RESPONSE_RECEIVED);

        foreach ($receivedDocuments as $document) {
            if (! $document->supplier_id) {
                throw ValidationException::withMessages(['supplier_response' => 'Hay un RFQ con respuesta sin proveedor definido. Identifica el proveedor antes de continuar.']);
            }

            $requiredItemIds = collect($document->selected_item_ids ?: $request->items->pluck('id')->all())
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique();

            $savedItemIds = $request->supplierBidResponses
                ->where('supplier_id', $document->supplier_id)
                ->flatMap(fn (SupplierBidResponse $response) => $response->items)
                ->pluck('quotation_request_item_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            $missing = $requiredItemIds->diff($savedItemIds);
            if ($missing->isNotEmpty()) {
                $references = $request->items
                    ->whereIn('id', $missing->all())
                    ->map(fn (QuotationRequestItem $item) => $item->part_number ?: $item->description ?: "Partida #{$item->id}")
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'supplier_response' => "La respuesta de {$document->supplier->name} está incompleta. Falta registrar: {$references}.",
                ]);
            }
        }

        $coverage = $this->responseCoverage($request);

        if (($coverage['responded'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['supplier_response' => 'Registra al menos una respuesta proveedor valida con precio, cantidad y entrega antes de seleccionar partidas.']);
        }
    }

    public function sendSelectionsToClientOffer(QuotationRequest $request, User $user): QuotationRequest
    {
        return $this->manageSelection->sendSelectionsToClientOffer($request, $user);
    }
}
