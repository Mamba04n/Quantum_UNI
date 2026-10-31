<?php

namespace App\Livewire\Ventas\Cotizaciones\CorreccionesOrden;

use App\Livewire\Ventas\Cotizaciones\Shared\BaseGestorCotizacion;
use App\Models\Entity;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Services\OrderTrackerService;
use App\Services\ClientOfferReviewService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class GestorCorreccionesOrden extends BaseGestorCotizacion
{
    public string $search = '';

    public function mount()
    {
        $this->activeTab = 'offer';
    }

    public function submitCorrections(ClientOfferReviewService $reviewService): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) return;

        // Todas las correcciones (tanto de admin como de cliente) cambian los precios/costos,
        // por lo que obligatoriamente deben pasar por la revisión de administración nuevamente.
        $reviewService->requestReview($quote, auth()->user(), 'Correcciones aplicadas a la oferta.');

        $this->selectedQuoteId = null;
        $this->showDetail = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Correcciones enviadas.']);
    }

    public function render(\App\Services\SalesWorkflowService $workflow)
    {
        abort_unless(auth()->user()?->canAny(['client_offer.view', 'order_approval.view']), 403);

        $statuses = [
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
        ];

        $quotes = QuotationRequest::with(['user', 'offerReviews' => function($q) {
                $q->latest()->limit(1);
            }])
            ->whereIn('status', $statuses)
            ->when($this->search, function ($q) {
                $q->where(function($sub) {
                    $sub->where('reference', 'like', "%{$this->search}%")
                        ->orWhere('quote_to_name', 'like', "%{$this->search}%");
                });
            })
            ->latest('updated_at')
            ->get();

        $selectedQuote = $this->selectedQuote();
        $products = Product::where('active', true)->limit(100)->get();
        $suppliers = Entity::where('type', 'proveedor')->orderBy('name')->get();

        return view('livewire.ventas.cotizaciones.correcciones-orden.gestor-correcciones-orden', [
            'quotes' => $quotes,
            'selectedQuote' => $selectedQuote,
            'products' => $products,
            'suppliers' => $suppliers,
        ]);
    }
}

