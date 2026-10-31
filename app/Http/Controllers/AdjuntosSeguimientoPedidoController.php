<?php

namespace App\Http\Controllers;

use App\Models\OrderTrackerAttachment;
use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;

class AdjuntosSeguimientoPedidoController extends Controller
{
    public function __construct(private readonly PrivateFileResponse $files) {}

    public function show(OrderTrackerAttachment $attachment, Request $request)
    {
        $this->authorizeAttachment($attachment);

        return $this->files->make(
            $attachment->file_path,
            $request,
            $attachment->file_name,
            allowInline: ! $request->boolean('download')
        );
    }

    private function authorizeAttachment(OrderTrackerAttachment $attachment): void
    {
        $user = auth()->user();

        $tracker = $attachment->tracker;
        abort_unless($tracker && $user?->can('view', $tracker), 403);

        $abilities = match ($attachment->phase) {
            'supplier_bid' => ['viewSupplierQuotes'],
            'client_offer', 'approval', 'direct_order', 'purchase_order' => ['viewSensitiveDocuments'],
            'invoice' => ['viewInvoices'],
            'kardex' => ['viewKardex'],
            default => ['view'],
        };

        abort_unless(collect($abilities)->contains(fn (string $ability) => $user->can($ability, $tracker)), 403);
    }
}
