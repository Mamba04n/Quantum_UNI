<?php

namespace App\Livewire\Administracion\Ventas\Rfq;

use App\Models\SupplierRfqDocument;
use App\Services\PdfService;
use App\Services\SupplierRfqReviewService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestorRevisionRfq extends Component
{
    public ?int $selectedReviewId = null;
    public string $reviewReason = '';

    public function selectReview(int $reviewId): void
    {
        abort_unless(auth()->user()?->can('rfq_review.view'), 403);

        $this->selectedReviewId = $reviewId;
        $this->reviewReason = '';
    }

    public function approve(SupplierRfqReviewService $service, PdfService $pdfService): void
    {
        $review = $this->selectedReview();
        if (! $review) {
            return;
        }

        try {
            $review = $service->approve($review, auth()->user(), $this->reviewReason ?: null);
            $pdfService->generateSupplierRfqPdf($review->fresh(), auth()->id(), $review->supplier);
            $this->reviewReason = '';
            $this->selectedReviewId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'RFQ aprobado y PDF generado. Queda pendiente marcarlo enviado al proveedor desde Licitacion.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function requestChanges(SupplierRfqReviewService $service): void
    {
        $review = $this->selectedReview();
        if (! $review) {
            return;
        }

        try {
            $this->validate(['reviewReason' => ['required', 'string', 'max:2000']]);
            $service->requestChanges($review, auth()->user(), $this->reviewReason);
            $this->reviewReason = '';
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud de cambios enviada al trabajador.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function reject(SupplierRfqReviewService $service): void
    {
        $review = $this->selectedReview();
        if (! $review) {
            return;
        }

        try {
            $this->validate(['reviewReason' => ['required', 'string', 'max:2000']]);
            $service->reject($review, auth()->user(), $this->reviewReason);
            $this->reviewReason = '';
            $this->dispatch('notify', ['type' => 'success', 'message' => 'RFQ rechazado.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('rfq_review.view'), 403);

        $reviews = SupplierRfqDocument::with([
            'quotationRequest.items',
            'quotationRequest.user',
            'supplier',
        ])
            ->where('status', SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW)
            ->latest('updated_at')
            ->get();

        $selectedReview = $this->selectedReviewId
            ? SupplierRfqDocument::with([
                'quotationRequest.items.clientMachine',
                'quotationRequest.user',
                'quotationRequest.supplierBidResponses.supplier',
                'quotationRequest.supplierRfqDocuments.supplier',
                'supplier.brands',
                'adminReviewer',
            ])->find($this->selectedReviewId)
            : null;

        return view('livewire.administracion.ventas.rfq.revision.gestor-revision-rfq', [
            'reviews' => $reviews,
            'selectedReview' => $selectedReview,
        ]);
    }

    private function selectedReview(): ?SupplierRfqDocument
    {
        return $this->selectedReviewId
            ? SupplierRfqDocument::with(['quotationRequest', 'supplier'])->find($this->selectedReviewId)
            : null;
    }
}
