<?php

namespace Tests\Feature;

use App\Models\{QuotationRequest, QuotationRequestItem};
use App\Services\ClientOfferDocumentMapper;
use App\Services\Pdf\PdfDocumentService;
use Tests\TestCase;

class ClientOfferPdfWarrantyTest extends TestCase
{
    public function test_selected_warranty_and_extension_are_mapped_without_supplier_override(): void
    {
        foreach (['A1' => '6-12 meses', 'A2' => '6 meses', 'B1' => '6 meses', 'B2' => '2-3 meses', 'C1' => '2 meses', 'C2' => '1 mes'] as $type => $term) {
            $item = $this->item($type);
            $mapped = app(ClientOfferDocumentMapper::class)->mapItem($item);
            $this->assertSame("$type ($term)", $mapped['warranty_label']);
            $item->has_warranty_extension = true;
            $this->assertSame("$type ($term) · Extensión Plus (+50%)", app(ClientOfferDocumentMapper::class)->mapItem($item)['warranty_label']);
        }
        $item = $this->item(null);
        $item->has_warranty_extension = true;
        $this->assertSame('Sin garantía', app(ClientOfferDocumentMapper::class)->mapItem($item)['warranty_label']);
    }

    public function test_all_issuer_templates_and_pdf_renderer_include_each_items_warranty(): void
    {
        $request = new QuotationRequest(['reference' => 'WARRANTY-CHECK', 'date' => '2026-11-03', 'currency' => 'NIO']);
        $request->setRelation('clientMachine', null);
        $plus = $this->item('A1');
        $plus->has_warranty_extension = true;
        $request->setRelation('items', collect([$plus, $this->item('C2'), $this->item(null)]));
        $data = ['request' => $request, 'mappedItems' => app(ClientOfferDocumentMapper::class)->map($request)['items'],
            'subtotal' => 300, 'iva' => 0, 'taxPercent' => 0, 'total' => 300, 'warranty' => null];
        foreach (['quantum' => 'pdf.client-offer-mpdf', 'powersales' => 'pdf.client-offer-powersales-mpdf', 'marcos_leiva' => 'pdf.client-offer-marcos-leiva-mpdf'] as $issuer => $template) {
            $data['company'] = ['key' => $issuer];
            $html = view($template, $data)->render();
            $this->assertStringContainsString('Garantía', $html);
            $this->assertStringContainsString('A1', $html);
            $this->assertStringContainsString('+50% Plus', $html);
            $this->assertStringContainsString('C2', $html);
            $this->assertStringContainsString('—', $html);
            $pdf = app(PdfDocumentService::class)->clientOfferContent($data);
            $this->assertStringStartsWith('%PDF-', $pdf);
            if (getenv('WARRANTY_PDF_EVIDENCE') === '1') {
                file_put_contents(storage_path('app/warranty-check-'.$issuer.'.pdf'), $pdf);
                file_put_contents(storage_path('app/warranty-check-'.$issuer.'.html'), $html);
            }
        }
    }

    private function item(?string $type): QuotationRequestItem
    {
        $item = new QuotationRequestItem(['part_name' => 'Filtro de aire', 'part_number' => 'TEST-001', 'quantity' => 1,
            'unit_price' => 100, 'warranty_type' => $type, 'has_warranty_extension' => false]);
        $item->setRelation('supplierBidSelection', null);
        $item->setRelation('product', null);
        return $item;
    }
}
