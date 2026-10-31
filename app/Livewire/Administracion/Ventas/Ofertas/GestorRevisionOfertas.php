<?php

namespace App\Livewire\Administracion\Ventas\Ofertas;

use App\Domain\Ventas\OfertasCliente\Queries\ListarRevisionesPendientesOfertaQuery;
use App\Domain\Ventas\OfertasCliente\Queries\ObtenerRevisionOfertaBasicaQuery;
use App\Domain\Ventas\OfertasCliente\Queries\ObtenerRevisionOfertaDetalleQuery;
use App\Models\ClientOfferReview;
use App\Services\ClientOfferReviewService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Validation\ValidationException;

#[Layout('layouts.app')]
class GestorRevisionOfertas extends Component
{
    public ?int $selectedReviewId = null;
    public string $reviewReason = '';

    public function selectReview(?int $reviewId = null): void
    {
        abort_unless(auth()->user()?->can('client_offer_review.view'), 403);

        $this->selectedReviewId = $reviewId;
        $this->reviewReason = '';
    }

    public function approve(ClientOfferReviewService $service): void
    {
        $review = $this->selectedReview();
        if (! $review) {
            return;
        }

        try {
            $service->approve($review, auth()->user(), $this->reviewReason ?: null);
            $this->reviewReason = '';
            $this->selectedReviewId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Oferta autorizada para enviar al cliente.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function requestChanges(ClientOfferReviewService $service): void
    {
        $review = $this->selectedReview();
        if (! $review) {
            return;
        }

        try {
            $this->validate(['reviewReason' => ['required', 'string', 'max:2000']]);
            $service->requestChanges($review, auth()->user(), $this->reviewReason);
            $this->reviewReason = '';
            $this->selectedReviewId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud de cambios enviada al trabajador.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function reject(ClientOfferReviewService $service): void
    {
        $review = $this->selectedReview();
        if (! $review) {
            return;
        }

        try {
            $this->validate(['reviewReason' => ['required', 'string', 'max:2000']]);
            $service->reject($review, auth()->user(), $this->reviewReason);
            $this->reviewReason = '';
            $this->selectedReviewId = null;
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Oferta rechazada.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('client_offer_review.view'), 403);

        return view('livewire.administracion.ventas.ofertas.revision.gestor-revision-ofertas', [
            'reviews' => app(ListarRevisionesPendientesOfertaQuery::class)->ejecutar(),
            'selectedReview' => app(ObtenerRevisionOfertaDetalleQuery::class)->ejecutar($this->selectedReviewId),
        ]);
    }

    private function selectedReview(): ?ClientOfferReview
    {
        return app(ObtenerRevisionOfertaBasicaQuery::class)->ejecutar($this->selectedReviewId);
    }
}
