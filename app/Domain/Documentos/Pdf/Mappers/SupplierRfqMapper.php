<?php

namespace App\Domain\Documentos\Pdf\Mappers;

use App\Models\SupplierRfqDocument;
use App\Services\Pdf\PdfDocumentService;
use App\Services\Pdf\RfqTextTranslator;
use App\Services\RfqPdfDataMapper;

class SupplierRfqMapper
{
    public function __construct(
        private readonly RfqPdfDataMapper $itemsMapper,
        private readonly PdfDocumentService $pdfDocumentService,
        private readonly RfqTextTranslator $translator
    ) {
    }

    public function map(SupplierRfqDocument $rfq, string $lang = 'es'): array
    {
        $isEnglish = $lang === 'en';

        $rfq->loadMissing([
            'quotationRequest.items.product',
            'quotationRequest.items.machine',
            'quotationRequest.clientMachine',
            'quotationRequest.user',
            'supplier',
        ]);

        $request = $rfq->quotationRequest;
        $supplier = $rfq->supplier;
        $hasSupplier = filled($supplier?->id);
        $generatedAt = $rfq->generated_at ?: now();
        $selectedItemIds = collect($rfq->selected_item_ids ?: [])->map(fn ($id) => (int) $id)->all();
        $items = $request?->items ?: collect();
        if (! empty($selectedItemIds)) {
            $items = $items->whereIn('id', $selectedItemIds)->values();
        }
        $itemSpecifications = $rfq->item_specifications ?: [];
        $itemQuantities = $rfq->rfq_item_quantities ?: [];
        $items->each(function ($item) use ($itemSpecifications, $itemQuantities) {
            $specification = data_get($itemSpecifications, (string) $item->id);
            if (filled($specification)) {
                $item->rfq_item_specifications = $specification;
            }
            if (array_key_exists((string) $item->id, $itemQuantities)) {
                $item->quantity = max(1, (int) $itemQuantities[$item->id]);
            }
        });

        return [
            'company' => [
                'name' => 'Quantum Soluciones S.A.',
                'address' => 'Managua, Nicaragua',
                'phone' => '+505 7669 8164',
                'email' => 'ventas@quantumsolucionesnic.com',
                'website' => 'quantumsolucionesnic.com',
                'logo_base64' => $this->pdfDocumentService->logoToBase64(public_path('images/logo-email.png')),
            ],
            'document' => [
                'rfq_number' => $rfq->effective_rfq_number,
                'date' => $isEnglish
                    ? $generatedAt->locale('en')->isoFormat('MMMM D, YYYY')
                    : $generatedAt->translatedFormat('d \d\e F \d\e Y'),
                'internal_reference' => $request?->reference ?? '-',
                'quote_to_name' => $isEnglish
                    ? ($this->translator->toEnglish($rfq->quote_to_name) ?: SupplierRfqDocument::DEFAULT_QUOTE_TO_NAME)
                    : ($rfq->quote_to_name ?: SupplierRfqDocument::DEFAULT_QUOTE_TO_NAME),
            ],
            'supplier' => [
                'has_supplier' => $hasSupplier,
                'name' => $supplier?->name ?? ($isEnglish ? 'To whom it may concern' : 'A quien corresponda'),
                'attention' => $supplier?->contact_name ?: ($hasSupplier ? ($isEnglish ? 'Sales Department' : 'Departamento de Ventas') : null),
                'email' => $supplier?->email ?: null,
                'phone' => $supplier?->phone_mobile ?: ($supplier?->phone_home ?: null),
            ],
            'consignee' => [
                'name' => $rfq->consignee_name ?: 'Quantum Soluciones S.A.',
                'contact_name' => $isEnglish ? $this->translator->toEnglish($rfq->consignee_contact_name) : $rfq->consignee_contact_name,
                'urgent_comment' => $isEnglish ? $this->translator->toEnglish($rfq->consignee_urgent_comment) : $rfq->consignee_urgent_comment,
                'address_1' => $rfq->consignee_address_1 ?: 'Rotonda Jean Paul Genie',
                'address_2' => $rfq->consignee_address_2 ?: 'Colonia Centroamerica',
                'country' => $rfq->consignee_country ?: 'Nicaragua',
                'city' => $rfq->consignee_city ?: 'Managua',
                'phone' => $rfq->consignee_phone ?: '+505 7669 8164',
                'emails' => $rfq->consignee_emails ?: 'compras@quantumsolucionesnic.com',
            ],
            'items' => $this->itemsMapper->mapItems($items),
            'specifications' => $isEnglish
                ? $this->translator->toEnglish($rfq->rfq_specifications)
                : $rfq->rfq_specifications,
            'responseRequirements' => $isEnglish
                ? $this->translator->toEnglish($rfq->effective_response_requirements)
                : $rfq->effective_response_requirements,
        ];
    }
}
