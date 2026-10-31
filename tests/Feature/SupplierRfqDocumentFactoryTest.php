<?php

namespace Tests\Feature;

use App\Domain\Documentos\Pdf\Support\SupplierRfqDocumentFactory;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use Tests\TestCase;

class SupplierRfqDocumentFactoryTest extends TestCase
{
    public function test_it_builds_transient_legacy_rfq_document_without_persisting_it(): void
    {
        $request = new QuotationRequest(['reference' => 'RFQ-TEST']);
        $supplier = new Entity(['name' => 'Proveedor de prueba']);

        $document = app(SupplierRfqDocumentFactory::class)->make(
            $request,
            [
                'rfq_id' => 'RFQ-TEST-001',
                'response_requirements' => 'Responder dentro de cinco dias.',
            ],
            $supplier
        );

        $this->assertFalse($document->exists);
        $this->assertSame('RFQ-TEST-001', $document->rfq_number);
        $this->assertSame('Quantum Soluciones S.A.', $document->consignee_name);
        $this->assertSame('Responder dentro de cinco dias.', $document->rfq_response_requirements);
        $this->assertTrue($document->relationLoaded('quotationRequest'));
        $this->assertTrue($document->relationLoaded('supplier'));
        $this->assertSame($request, $document->quotationRequest);
        $this->assertSame($supplier, $document->supplier);
        $this->assertSame(config('company.quote_to_name', 'Quantum Soluciones S.A. - Compras'), $document->quote_to_name);
    }
}
