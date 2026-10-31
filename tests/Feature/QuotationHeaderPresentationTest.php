<?php

namespace Tests\Feature;

use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class QuotationHeaderPresentationTest extends TestCase
{
    private array $permissions = [];

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn ($user, $ability) => in_array($ability, $this->permissions, true));
    }

    private function toolbar(string $stage, array $permissions = [], bool $cancelled = false): string
    {
        $this->permissions = $permissions;
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->andReturnUsing(fn ($ability) => in_array($ability, $this->permissions, true));
        $this->actingAs($user);
        $quote = new QuotationRequest;
        $quote->id = 31;
        $quote->status = $cancelled ? QuotationRequest::STATUS_CANCELLED : QuotationRequest::STATUS_RECEIVED;

        $data = [
            'currentStage' => $stage, 'selectedQuote' => $quote,
            'selectedSupplierId' => 1, 'canEditOfferData' => false,
        ];

        return view('livewire.ventas.cotizaciones.fase-cotizacion.partials.context-actions', $data)->render()
            . view('livewire.ventas.cotizaciones.fase-cotizacion.partials.action-buttons', $data)->render();
    }

    public function test_each_real_stage_uses_its_correct_workflow_position(): void
    {
        foreach (['supplier_bid' => 1, 'supplier_response' => 2, 'client_offer' => 3, 'customer_approval' => 4, 'purchase_preparation' => 5, 'purchase_order_generated' => 5] as $stage => $position) {
            $html = $this->toolbar($stage);
            // The same visible pipeline serves desktop and mobile.
            $this->assertSame(1, substr_count($html, 'aria-current="step"'), $stage);
            $this->assertSame($position - 1, substr_count($html, 'data-step-state="completed"'), $stage);
            $this->assertSame(5 - $position, substr_count($html, 'data-step-state="future"'), $stage);
            $this->assertStringNotContainsString('wire:click="advanceStage', $html);
        }
    }

    public function test_header_keeps_editing_in_every_phase_and_allows_cancellation(): void
    {
        foreach (['supplier_bid', 'supplier_response', 'client_offer', 'customer_approval', 'purchase_preparation'] as $stage) {
            $html = $this->toolbar($stage);
            $this->assertStringContainsString('Modificar orden', $html);
            $this->assertStringNotContainsString('Revisar respuesta', $html);
            $this->assertStringNotContainsString('Guardar respuesta', $html);
            $this->assertStringNotContainsString('>Documentos<', $html);
            $this->assertStringContainsString('Cancelar cotización', $html);
        }
    }
    public function test_cancelled_record_has_no_active_or_completed_stage(): void
    {
        $html = $this->toolbar('customer_request', ['customer_request.cancel'], true);
        $this->assertStringNotContainsString('aria-current="step"', $html);
        $this->assertStringNotContainsString('data-step-state="completed"', $html);
        $this->assertStringNotContainsString('Cancelar cotización', $html);
    }
}
