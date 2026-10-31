<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor;
use App\Models\QuotationRequest;
use Tests\TestCase;

class QuotationStartsAtRfqTest extends TestCase
{
    public function test_existing_requests_open_in_rfq_without_changing_stored_status(): void
    {
        $component = new GestorLicitacionProveedor;
        foreach ([QuotationRequest::STATUS_RECEIVED, QuotationRequest::STATUS_IN_REVIEW, QuotationRequest::STATUS_PENDING_BID] as $status) {
            $quote = new QuotationRequest(['status' => $status]);
            $quote->id = 3;
            $quote->setRelation('purchaseOrders', collect());
            $this->assertSame('supplier_bid', $component->stageKeyForQuote($quote));
            $this->assertSame(route('sales.quotations.licitacion', ['quoteId' => 3]), $component->getQuoteUrl($quote));
            $this->assertNull($component->previousStageInfo($quote));
            $this->assertSame($status, $quote->status);
        }
        $this->assertArrayNotHasKey('customer_request', $component->flowStages);
        $this->assertArrayNotHasKey('sales.quotations.solicitud', config('workspaces.compras.items'));
    }

    public function test_old_urls_redirect_to_the_same_record(): void
    {
        $this->withoutMiddleware();
        $this->get('/sales/quotations/solicitud/3')->assertRedirect(route('sales.quotations.licitacion', ['quoteId' => 3]));
        $this->get('/sales/quotations/solicitud')->assertRedirect(route('sales.quotations.licitacion'));
    }
}
