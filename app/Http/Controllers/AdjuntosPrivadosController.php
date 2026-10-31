<?php

namespace App\Http\Controllers;

use App\Models\ClientMachine;
use App\Models\Entity;
use App\Models\OrderStatusDocument;
use App\Models\QuotationRequest;
use App\Models\StockMove;
use App\Models\StockMoveAttachment;
use App\Models\TicketMessage;
use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;

class AdjuntosPrivadosController extends Controller
{
    public function __construct(private readonly PrivateFileResponse $files) {}

    public function customerRequestEvidence(QuotationRequest $quotationRequest, Request $request)
    {
        $this->authorize('view', $quotationRequest);

        return $this->files->make($quotationRequest->evidence_attachment_path, $request);
    }

    public function stockMoveAttachment(StockMove $stockMove, Request $request)
    {
        $this->authorize('viewDocuments', $stockMove);

        return $this->files->make($stockMove->attachment_path, $request);
    }

    public function stockMoveAttachmentFile(StockMoveAttachment $attachment, Request $request)
    {
        $this->authorize('viewDocuments', $attachment->stockMove);

        return $this->files->make($attachment->file_path, $request, $attachment->file_name);
    }

    public function ticketMessageAttachment(TicketMessage $message, Request $request)
    {
        $this->authorize('view', $message->ticket);

        return $this->files->make($message->attachment_path, $request);
    }

    public function clientMachineImage(ClientMachine $machine, Request $request)
    {
        $this->authorize('view', $machine);

        return $this->files->make($machine->image_path, $request, allowInline: true);
    }

    public function orderStatusDocument(OrderStatusDocument $document, Request $request)
    {
        $status = $document->orderStatus;
        $tracker = $status?->tracker;

        abort_unless($tracker && $request->user()->can('view', $tracker), 403);
        abort_unless(
            $request->user()->can('viewSensitiveDocuments', $tracker)
            || $request->user()->can('viewInvoices', $tracker)
            || $request->user()->can('viewKardex', $tracker),
            403
        );

        return $this->files->make($document->file_path, $request, $document->file_name);
    }

    public function customerOrderStatusDocument(OrderStatusDocument $document, Request $request)
    {
        $user = $request->user();
        $document->loadMissing('orderStatus.saleOrder');
        $entityIds = Entity::query()
            ->where('type', 'cliente')
            ->where('user_id', $user->id)
            ->pluck('id');
        $saleOrder = $document->orderStatus?->saleOrder;

        abort_unless(
            $entityIds->contains($document->orderStatus?->customer_id)
            || ($saleOrder && $entityIds->contains($saleOrder->customer_id)),
            403
        );

        return $this->files->make($document->file_path, $request, $document->file_name);
    }
}
