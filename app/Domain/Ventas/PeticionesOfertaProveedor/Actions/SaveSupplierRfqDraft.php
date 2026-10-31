<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Actions;

use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSupplierRfqDraft
{
    public function execute(QuotationRequest $quotation, ?Entity $supplier, array $data, User $user): SupplierRfqDocument
    {
        return DB::transaction(function () use ($quotation, $supplier, $data) {
            $rfqDocumentId = $data['rfq_document_id'] ?? null;
            $document = null;

            if ($rfqDocumentId) {
                $document = SupplierRfqDocument::where('quotation_request_id', $quotation->id)
                    ->whereKey($rfqDocumentId)
                    ->lockForUpdate()
                    ->first();
            } else {
                $query = SupplierRfqDocument::where('quotation_request_id', $quotation->id)
                    ->where('status', SupplierRfqDocument::STATUS_DRAFT)
                    ->whereNull('pdf_path');

                $document = ($supplier ? $query->where('supplier_id', $supplier->id) : $query->whereNull('supplier_id'))
                    ->lockForUpdate()
                    ->first();
            }

            if ($document && ! $document->isEditable()) {
                throw ValidationException::withMessages(['rfq' => 'Este RFQ ya no se puede editar en su estado actual.']);
            }

            $payload = $this->sanitizeDraftData($data, $quotation);
            $payload['quotation_request_id'] = $quotation->id;
            $payload['supplier_id'] = $supplier?->id;

            if ($document) {
                $payload['rfq_number'] = $document->rfq_number ?: $this->makeRfqNumber($quotation, $supplier);
                $payload['status'] = $document->status ?: SupplierRfqDocument::STATUS_DRAFT;
                $document->update($payload);
            } else {
                $payload['rfq_number'] = $this->makeRfqNumber($quotation, $supplier);
                $payload['status'] = SupplierRfqDocument::STATUS_DRAFT;
                $document = SupplierRfqDocument::create($payload);
            }

            return $document->fresh(['quotationRequest.items', 'supplier', 'adminReviewer', 'generator']);
        });
    }

    private function sanitizeDraftData(array $data, QuotationRequest $quotation): array
    {
        $payload = collect([
            'quote_to_name',
            'consignee_name',
            'consignee_contact_name',
            'consignee_comment',
            'consignee_urgent_comment',
            'consignee_address_1',
            'consignee_address_2',
            'consignee_country',
            'consignee_city',
            'consignee_phone',
            'consignee_emails',
            'rfq_response_requirements',
            'rfq_specifications',
        ])->mapWithKeys(fn (string $field) => [$field => filled($data[$field] ?? null) ? trim((string) $data[$field]) : null])->all();

        $payload['quote_to_name'] = $payload['quote_to_name'] ?: config('company.quote_to_name', 'Quantum Soluciones S.A. - Compras');
        $payload['consignee_name'] = $payload['consignee_name'] ?: config('company.legal_name', 'Quantum Soluciones S.A.');
        $payload['consignee_contact_name'] = $payload['consignee_contact_name'] ?: (auth()->user()?->name ?: config('company.contact_name', 'Departamento de Compras'));
        $payload['consignee_address_1'] = $payload['consignee_address_1'] ?: config('company.address_1', 'Rotonda Jean Paul Genie 100m al Este, 50m al Sur');
        $payload['consignee_address_2'] = $payload['consignee_address_2'] ?: config('company.address_2', 'Edificio Quantum, Managua');
        $payload['consignee_country'] = $payload['consignee_country'] ?: config('company.country', 'Nicaragua');
        $payload['consignee_city'] = $payload['consignee_city'] ?: config('company.city', 'Managua');
        $payload['consignee_phone'] = $payload['consignee_phone'] ?: config('company.phone', '+505 7669 8164');
        $payload['consignee_emails'] = $payload['consignee_emails'] ?: config('company.purchasing_emails', 'compras@quantumsolucionesnic.com, operaciones@quantumsolucionesnic.com');
        $payload['rfq_response_requirements'] = $payload['rfq_response_requirements'] ?: SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS;

        $selectedItemIds = array_key_exists('selected_item_ids', $data)
            ? $data['selected_item_ids']
            : $quotation->items()->pluck('id')->all();

        $payload['selected_item_ids'] = collect($selectedItemIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $payload['item_specifications'] = collect($data['item_specifications'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => filled($value) ? trim((string) $value) : null])
            ->filter()
            ->all();

        $payload['rfq_item_quantities'] = collect($data['rfq_item_quantities'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => max(1, (int) $value)])
            ->all();

        return $payload;
    }

    private function makeRfqNumber(QuotationRequest $quotation, ?Entity $supplier): string
    {
        $base = sprintf(
            'RFQ-%s-%05d-%s',
            now()->format('y'),
            (int) $quotation->id,
            $supplier ? sprintf('%04d', (int) $supplier->id) : 'GEN'
        );

        $existingCount = SupplierRfqDocument::where('quotation_request_id', $quotation->id)
            ->where('rfq_number', 'like', $base.'%')
            ->count();

        if ($existingCount > 0) {
            return sprintf('%s-%02d', $base, $existingCount + 1);
        }

        return $base;
    }
}
