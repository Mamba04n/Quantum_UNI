<?php

namespace App\Http\Controllers;

use App\Models\QuotationRequest;
use App\Support\QuotationCover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuotationCoverController extends Controller
{
    public function __invoke(Request $request, QuotationRequest $quotationRequest, QuotationCover $covers)
    {
        $this->authorize('view', $quotationRequest);
        $cover = $covers->resolve($quotationRequest, auth()->user()->can('supplier_bid.view'));
        abort_unless($cover, 404);

        $disk = Storage::disk($cover['disk']);
        $lastModified = $disk->lastModified($cover['path']);
        $etag = '"' . md5($cover['path'] . $lastModified) . '"';

        if ($request->header('If-None-Match') === $etag || $request->header('If-Modified-Since') === gmdate('D, d M Y H:i:s', $lastModified) . ' GMT') {
            return response('', 304, [
                'Cache-Control' => 'private, max-age=86400, must-revalidate',
                'ETag' => $etag,
            ]);
        }

        return $disk->response($cover['path'], null, [
            'Cache-Control' => 'private, max-age=86400, must-revalidate',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }
}

