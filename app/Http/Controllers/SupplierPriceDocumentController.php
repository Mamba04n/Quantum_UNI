<?php

namespace App\Http\Controllers;

use App\Models\SupplierPriceQuotation;
use App\Services\SupplierPriceImport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SupplierPriceDocumentController extends Controller
{
    public function download(SupplierPriceQuotation $quotation)
    {
        Gate::authorize('document', $quotation);
        abort_unless($quotation->attachment_path && str_starts_with($quotation->attachment_path, 'supplier-price-quotations/') && ! str_contains($quotation->attachment_path, '..'), 404);
        abort_unless(Storage::disk('private')->exists($quotation->attachment_path), 404);

        return Storage::disk('private')->download($quotation->attachment_path, $quotation->attachment_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function template()
    {
        Gate::authorize('create', SupplierPriceQuotation::class);

        return response("\xEF\xBB\xBF".implode(',', SupplierPriceImport::HEADERS)."\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-precios-proveedor.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
