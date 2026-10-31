<?php

namespace App\Domain\Documentos\Pdf\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PdfDocumentService
{
    public function renderSupplierRfq(array $data, string $relativePath): string
    {
        $absolutePath = Storage::disk('private')->path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $this->makeSupplierRfq($data)->Output($absolutePath, Destination::FILE);

        return $relativePath;
    }

    public function supplierRfqContent(array $data): string
    {
        return $this->makeSupplierRfq($data)->Output('', Destination::STRING_RETURN);
    }

    public function renderClientOffer(array $data, string $relativePath): string
    {
        $absolutePath = Storage::disk('private')->path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $this->makeClientOffer($data)->Output($absolutePath, Destination::FILE);

        return $relativePath;
    }

    public function clientOfferContent(array $data): string
    {
        return $this->makeClientOffer($data)->Output('', Destination::STRING_RETURN);
    }

    public function renderPurchaseOrderProvider(array $data, string $relativePath): string
    {
        $absolutePath = Storage::disk('private')->path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $this->makePurchaseOrderProvider($data)->Output($absolutePath, Destination::FILE);

        return $relativePath;
    }

    public function renderPackingList(array $data, string $relativePath): string
    {
        $absolutePath = Storage::disk('private')->path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        $this->makePackingList($data)->Output($absolutePath, Destination::FILE);

        return $relativePath;
    }

    public function logoToBase64(?string $path = null): ?string
    {
        $paths = collect([
            $path,
            public_path('assets/images/logo completo.png'),
            public_path('assets/images/logo-completo.png'),
            public_path('storefront/images/logo-completo.png'),
            public_path('storefront/images/logo.png'),
            public_path('images/logo-email.png'),
            public_path('images/logo completo.png'),
        ])->filter()->unique();

        foreach ($paths as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $mime = mime_content_type($candidate) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($candidate));
        }

        logger()->warning('Logo PDF no encontrado', ['paths' => $paths->values()->all()]);

        return null;
    }

    private function makeDocument(array $options = []): Mpdf
    {
        return new Mpdf(array_merge([
            'mode' => 'utf-8',
            'format' => 'Letter',
            'default_font' => 'dejavusans',
            'tempDir' => storage_path('app/mpdf-temp'),
        ], $options));
    }

    private function makeSupplierRfq(array $data): Mpdf
    {
        $html = view('pdf.supplier-rfq-mpdf', $data)->render();

        $mpdf = $this->makeDocument([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 16,
            'margin_header' => 4,
            'margin_footer' => 5,
        ]);

        $mpdf->SetTitle($data['document']['rfq_number'] ?? 'RFQ Proveedor');
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    private function makeClientOffer(array $data): Mpdf
    {
        $companyKey = $data['company']['key'] ?? $data['request']->company_issuer ?? 'quantum';
        $view = match ($companyKey) {
            'powersales' => 'pdf.client-offer-powersales-mpdf',
            'marcos_leiva' => 'pdf.client-offer-marcos-leiva-mpdf',
            default => 'pdf.client-offer-mpdf',
        };

        $html = view($view, $data)->render();

        $mpdf = $this->makeDocument([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'margin_header' => 4,
            'margin_footer' => 4,
        ]);

        $mpdf->SetTitle('Oferta Cliente '.$data['request']->reference);
        $mpdf->WriteHTML($html);

        if ($companyKey === 'quantum') {
            $this->appendClientOfferWarrantyAnnex($mpdf, $data);
        }

        return $mpdf;
    }

    private function appendClientOfferWarrantyAnnex(Mpdf $mpdf, array $data): void
    {
        $garantiasPath = collect([
            resource_path('pdf/GARANTIA.pdf'),
            base_path('GARANTIA.pdf'),
            base_path('Garantias.pdf'),
        ])->first(fn (string $path) => file_exists($path));

        if ($garantiasPath) {
            try {
                $pagecount = $mpdf->SetSourceFile($garantiasPath);
                for ($i = 1; $i <= $pagecount; $i++) {
                    $mpdf->AddPage();
                    $tplId = $mpdf->ImportPage($i);
                    $mpdf->UseTemplate($tplId);
                }

                return;
            } catch (\Exception $e) {
                logger()->error('Error appending garantia PDF: '.$e->getMessage(), [
                    'path' => $garantiasPath,
                ]);
            }
        }
    }

    private function makePurchaseOrderProvider(array $data): Mpdf
    {
        $html = view('pdf.purchase-order-provider-mpdf', $data)->render();

        $mpdf = $this->makeDocument([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 18,
            'margin_header' => 4,
            'margin_footer' => 4,
        ]);

        $mpdf->SetTitle('Orden de Compra '.$data['data']['document']['code']);
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    private function makePackingList(array $data): Mpdf
    {
        $html = view('pdf.packing-list-mpdf', $data)->render();

        $mpdf = $this->makeDocument([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 18,
            'margin_header' => 4,
            'margin_footer' => 4,
        ]);

        $mpdf->SetTitle('Lista de empaque '.$data['order']['reference']);
        $mpdf->WriteHTML($html);

        return $mpdf;
    }
}
