<?php

namespace App\Domain\Documentos\Pdf\Support;

use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;

class SupplierRfqDocumentFactory
{
    /**
     * Build the transient document used by legacy RFQ generation.
     *
     * The returned model is intentionally not persisted.
     */
    public function make(
        QuotationRequest $request,
        array $documentData,
        ?Entity $supplier = null
    ): SupplierRfqDocument {
        $document = new SupplierRfqDocument([
            'rfq_number' => $documentData['rfq_id'],
            'quote_to_name' => config('company.quote_to_name', 'Quantum Soluciones S.A. - Compras'),
            'consignee_name' => config('company.legal_name', 'Quantum Soluciones S.A.'),
            'consignee_contact_name' => config('company.contact_name', 'Departamento de Compras'),
            'consignee_address_1' => config('company.address_1', 'Rotonda Jean Paul Genie 100m al Este, 50m al Sur'),
            'consignee_address_2' => config('company.address_2', 'Edificio Quantum, Managua'),
            'consignee_country' => config('company.country', 'Nicaragua'),
            'consignee_city' => config('company.city', 'Managua'),
            'consignee_phone' => config('company.phone', '+505 7669 8164'),
            'consignee_emails' => config('company.purchasing_emails', 'compras@quantumsolucionesnic.com, operaciones@quantumsolucionesnic.com'),
            'rfq_response_requirements' => $documentData['response_requirements']
                ?? SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS,
        ]);

        $document->setRelation('quotationRequest', $request);

        if ($supplier) {
            $document->setRelation('supplier', $supplier);
        }

        return $document;
    }
}
