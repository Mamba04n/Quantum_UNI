<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierRfqDocument;
use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\ExchangeRateService;
use App\Services\InventoryReservationService;
use App\Services\OrderTrackerService;
use App\Services\PdfService;
use App\Services\PurchasePreparationService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidResponseService;
use App\Services\SupplierRfqReviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ManagesProcessComments
{
    public array $commentFiles = [];

    protected function authorizeCommentAccess(): void
    {
        $quote = $this->selectedQuote();
        abort_unless($quote, 404);
    }

    public function removeCommentFile(int $index): void
    {
        unset($this->commentFiles[$index]);
        $this->commentFiles = array_values($this->commentFiles);
    }

    public function downloadCommentFile(int $id, OrderTrackerService $trackerService)
    {
        $this->authorizeCommentAccess();
        $quote = $this->selectedQuote();
        abort_unless($quote, 404);
        $tracker = $trackerService->trackerForQuotation($quote, false);
        abort_unless($tracker, 404);
        $attachment = $tracker->attachments()->whereHas('activity.comments')->findOrFail($id);
        abort_unless(Storage::disk('private')->exists($attachment->file_path), 404);

        return Storage::disk('private')->download($attachment->file_path, $attachment->file_name);
    }

    public function addComment(OrderTrackerService $trackerService): void
    {
        $this->authorizeCommentAccess();
        $this->validate([
            'newComment' => ['required_without:commentFiles', 'string', 'max:2000'],
            'commentVisibility' => ['required', 'in:internal,customer'],
            'commentFiles' => ['array', 'max:5'],
            'commentFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt', 'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ]);

        $quote = $this->selectedQuote();
        if (!$quote) return;

        $tracker = $trackerService->trackerForQuotation($quote);
        if ($tracker) {
            $storedPaths = [];
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($trackerService, $tracker, &$storedPaths) {
                    $comment = $trackerService->addComment($tracker, $this->newComment, $this->commentVisibility);
                    $phase = $this->stageKeyForQuote($this->selectedQuote());
                    $comment->activity->update(['phase' => $phase]);
                    foreach ($this->commentFiles as $file) {
                        $path = $file->store('order-tracker/comments', 'private');
                        if (! $path) {
                            throw new \RuntimeException('Unable to store comment attachment.');
                        }
                        $storedPaths[] = $path;
                        $tracker->attachments()->create([
                            'activity_id' => $comment->activity_id,
                            'phase' => $phase,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'file_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'uploaded_by' => auth()->id(),
                        ]);
                    }
                });
            } catch (Throwable $exception) {
                Storage::disk('private')->delete($storedPaths);
                report($exception);
                $this->addError('commentFiles', 'No se pudo guardar el comentario con sus archivos. Intenta de nuevo.');
                return;
            }
            $this->newComment = '';
            $this->commentFiles = [];
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Comentario agregado al proceso.']);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontró el tracker para esta solicitud.']);
        }
    }
}
