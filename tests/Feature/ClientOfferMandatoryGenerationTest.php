<?php

namespace Tests\Feature;

use App\Domain\Ventas\LicitacionesProveedor\Actions\ManageSupplierBidSelection;
use App\Livewire\Ventas\Cotizaciones\OfertaCliente\GestorOfertaCliente;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ClientOfferMandatoryGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private QuotationRequest $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'sales_mandatory@example.com',
            'is_active' => true,
        ]);
        $adminRole = Role::firstOrCreate(
            ['slug' => User::ROLE_ADMIN],
            ['name' => 'Administrador']
        );
        $this->user->roles()->attach($adminRole->id);

        $this->quote = QuotationRequest::factory()->create([
            'user_id' => $this->user->id,
            'reference' => 'SC-MANDATORY-TEST',
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'commercial_phase' => 'client_offer',
            'currency' => 'NIO',
            'tax_percent' => 15,
            'subtotal' => 5000,
            'total_amount' => 5750,
        ]);
    }

    public function test_cannot_advance_from_client_offer_without_generating_pdf(): void
    {
        $test = Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id]);

        // Attempting to advance should trigger validation error because no PDF has been generated
        $test->call('advanceStage', $this->quote->id);

        $this->assertNotNull($test->get('advanceStageError'));
        $this->assertStringContainsString('Es obligatorio generar el PDF de la Oferta Cliente', $test->get('advanceStageError'));

        // Quotation must remain in client_offer phase
        $this->assertEquals(QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, $this->quote->fresh()->status);
        $this->assertEquals('client_offer', $this->quote->fresh()->commercial_phase);
    }

    public function test_cannot_transition_workflow_to_client_response_pending_without_offer_pdf(): void
    {
        $workflow = app(\App\Domain\Ventas\FlujoTrabajo\Services\SalesWorkflowService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No se puede avanzar a respuesta del cliente sin haber generado la Oferta Cliente (PDF).');

        $workflow->transition($this->quote, QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING, $this->user, true);
    }

    public function test_can_advance_after_generating_offer_pdf(): void
    {
        // Manually simulate generated PDF
        $this->quote->forceFill([
            'client_offer_pdf_path' => 'commercial/client-offers/test.pdf',
            'client_offer_generated_at' => now(),
            'status' => QuotationRequest::STATUS_FINAL_PDF_GENERATED,
        ])->save();

        $offerVersion = \App\Models\ClientOfferVersion::create([
            'quotation_request_id' => $this->quote->id,
            'version_number' => 1,
            'reference' => 'SC-MANDATORY-TEST-V1',
            'status' => 'draft',
            'currency' => 'NIO',
            'subtotal' => 5000,
            'tax_amount' => 750,
            'total_amount' => 5750,
            'created_by' => $this->user->id,
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id]);

        $test->call('advanceStage', $this->quote->id);

        $this->assertNull($test->get('advanceStageError'));
        $this->assertEquals(QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING, $this->quote->fresh()->status);
        $this->assertEquals('sent_to_customer', $offerVersion->fresh()->status);
    }

    public function test_customer_approval_panel_blocks_approval_if_offer_missing(): void
    {
        // Move to customer approval without pdf (simulating existing flawed records)
        $this->quote->forceFill([
            'status' => QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            'commercial_phase' => 'customer_approval',
            'client_offer_pdf_path' => null,
        ])->save();

        $test = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Ventas\Cotizaciones\AprobacionCliente\GestorAprobacionCliente::class, ['quoteId' => $this->quote->id]);

        $html = $test->html();
        $this->assertStringContainsString('No se ha generado el PDF de la Oferta Cliente para esta solicitud', $html);
        $this->assertStringContainsString('Generar PDF de Oferta ahora', $html);

        // Attempting to register approval should notify error without crashing
        $test->call('registerClientApproval', 'total');
        $test->assertDispatched('notify');
    }

    public function test_customer_tax_can_be_zero_and_pdf_receives_the_saved_tax(): void
    {
        $pdf = \Mockery::mock(\App\Services\PdfService::class);
        $pdf->shouldReceive('generateClientOfferPdf')->once()->withArgs(function ($quote, $userId) {
            return (float) $quote->tax_percent === 0.0 && $userId === $this->user->id;
        })->andReturn('test/offer.pdf');
        $this->app->instance(\App\Services\PdfService::class, $pdf);

        $test = Livewire::actingAs($this->user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $this->quote->id])
            ->assertSee('Sin impuesto (0%)')
            ->set('clientOfferTaxPercent', '0')
            ->call('generateClientOfferPdf')->assertHasNoErrors();

        $this->assertEquals(0, $this->quote->fresh()->tax_percent);
        $this->quote->forceFill(['client_offer_pdf_path' => 'old-offer.pdf', 'client_offer_generated_at' => now()])->save();
        $test->set('clientOfferTaxPercent', '15')->call('saveClientOfferTaxPercent')->assertHasNoErrors();
        $this->assertEquals(15, $this->quote->fresh()->tax_percent);
        $this->assertNull($this->quote->fresh()->client_offer_pdf_path);
    }
}
