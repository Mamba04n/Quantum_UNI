<?php

namespace App\Http\Controllers;

use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Services\Pdf\Mappers\SupplierRfqMapper;
use App\Services\Pdf\PdfDocumentService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PdfCotizacionController extends Controller
{
    public function generate(int $id, Request $request, PdfService $pdfService)
    {
        $quotation = QuotationRequest::with('items', 'user')->findOrFail($id);
        $type = $request->query('type', 'client_offer');

        if ($type === 'rfq') {
            abort_unless(auth()->user()?->can('supplier_bid.view_supplier_documents') || auth()->user()?->can('supplier_bid.create'), 403);
            $path = $quotation->rfq_pdf_path ?: $pdfService->generateSupplierRFQPdf($quotation, auth()->id());

            return $pdfService->outputStoredPdf($path, 'RFQ_'.$quotation->reference.'.pdf', $request->boolean('download'));
        }

        abort_unless(
            auth()->user()?->can('supplier_bid.view')
            || auth()->user()?->can('tracker.view_supplier_quotes')
            || auth()->user()?->can('internal_approval.view_documents'),
            403
        );
        $path = $quotation->client_offer_pdf_path ?: $pdfService->generateClientOfferPdf($quotation, auth()->id());

        return $pdfService->outputStoredPdf($path, 'Oferta_'.$quotation->reference.'.pdf', $request->boolean('download'));
    }

    public function supplierRfqDocument(
        SupplierRfqDocument $document,
        Request $request,
        PdfService $pdfService,
        PdfDocumentService $pdfDocumentService,
        SupplierRfqMapper $supplierRfqMapper
    ) {
        abort_unless(
            auth()->user()?->can('supplier_bid.view_supplier_documents')
            || auth()->user()?->can('supplier_bid.view')
            || auth()->user()?->can('supplier_bid.create')
            || auth()->user()?->can('tracker.view_supplier_quotes')
            || auth()->user()?->can('internal_approval.view_documents'),
            403
        );

        abort_unless(filled($document->pdf_path), 404);

        $lang = $request->query('lang') === 'en' ? 'en' : 'es';

        if ($lang === 'en') {
            $data = $supplierRfqMapper->map($document, 'en');
            $data['lang'] = 'en';
            $fileName = Str::ascii($document->effective_rfq_number.'_EN.pdf') ?: 'RFQ_EN.pdf';

            return response($pdfDocumentService->supplierRfqContent($data), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    $request->boolean('download') ? 'attachment' : 'inline',
                    $fileName
                ),
                'Content-Security-Policy' => "sandbox; default-src 'none'",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }

        return $pdfService->outputStoredPdf(
            $document->pdf_path,
            $document->effective_rfq_number.'_ES.pdf',
            $request->boolean('download')
        );
    }
}
