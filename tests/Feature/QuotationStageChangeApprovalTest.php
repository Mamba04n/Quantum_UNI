<?php

namespace Tests\Feature;

use App\Domain\Pedidos\Tracking\Services\OrderTrackerService;
use App\Domain\Ventas\FlujoTrabajo\Queries\ListPendingQuotationStageChanges;
use App\Domain\Ventas\FlujoTrabajo\Services\QuotationStageChangeService;
use App\Domain\Ventas\FlujoTrabajo\Services\SalesWorkflowService;
use App\Models\OrderTrackerActivity;
use App\Models\QuotationRequest;
use App\Models\QuotationStageChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QuotationStageChangeApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_revert_does_not_change_stage_until_admin_approves_it(): void
    {
        $requester = User::factory()->create();
        $admin = User::factory()->create();
        $quote = $this->quote();
        $service = app(QuotationStageChangeService::class);

        $request = $service->requestRevert($quote, $this->transition(), $requester, 'Se seleccionó la etapa incorrecta y debe corregirse.');

        $this->assertSame(QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, $quote->fresh()->status);
        $this->assertSame(QuotationStageChangeRequest::STATUS_PENDING, $request->status);

        $service->approve($request, $admin, 'Revisado con el responsable comercial.');

        $this->assertSame(QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, $quote->fresh()->status);
        $this->assertSame(QuotationStageChangeRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->reviewed_by);
    }

    public function test_duplicate_pending_request_is_blocked(): void
    {
        $requester = User::factory()->create();
        $quote = $this->quote();
        $service = app(QuotationStageChangeService::class);
        $service->requestRevert($quote, $this->transition(), $requester, 'Primer motivo válido para solicitar el retroceso.');

        $this->expectException(ValidationException::class);
        $service->requestRevert($quote, $this->transition(), $requester, 'Segundo motivo que no debe generar otra solicitud.');
    }

    public function test_request_records_pending_data_without_mutating_the_quote(): void
    {
        $quote = $this->quote();
        $request = app(QuotationStageChangeService::class)->requestRevert(
            $quote,
            $this->transition(),
            User::factory()->create(),
            'Motivo suficientemente detallado para regresar la etapa.',
        );

        $this->assertSame(QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, $quote->fresh()->status);
        $this->assertSame($quote->status, $request->from_status);
        $this->assertSame($this->transition()['target_status'], $request->to_status);
        $this->assertDatabaseHas('order_tracker_activities', ['action' => 'commercial_stage.revert_requested']);
    }

    public function test_approve_updates_quote_request_tracker_and_records_one_approval_activity(): void
    {
        [$quote, $request, $admin] = $this->pendingRequest();

        app(QuotationStageChangeService::class)->approve($request, $admin, 'Aprobado por administración.');

        $this->assertSame($request->to_status, $quote->fresh()->status);
        $this->assertSame(QuotationStageChangeRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertDatabaseHas('order_trackers', ['operational_status' => $request->to_status]);
        $this->assertSame(1, OrderTrackerActivity::where('action', 'commercial_stage.reverted_approved')->count());
    }

    public function test_approve_rejects_an_already_processed_request(): void
    {
        [, $request, $admin] = $this->pendingRequest();
        $request->update(['status' => QuotationStageChangeRequest::STATUS_REJECTED]);

        $this->expectException(ValidationException::class);
        app(QuotationStageChangeService::class)->approve($request, $admin);
    }

    public function test_approve_rejects_a_request_when_quote_stage_changed(): void
    {
        [$quote, $request, $admin] = $this->pendingRequest();
        $quote->update(['status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED]);

        $this->expectException(ValidationException::class);
        app(QuotationStageChangeService::class)->approve($request, $admin);
    }

    public function test_reject_preserves_quote_and_records_notes_and_activity(): void
    {
        [$quote, $request, $admin] = $this->pendingRequest();

        app(QuotationStageChangeService::class)->reject($request, $admin, 'La corrección no corresponde.');

        $this->assertSame(QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, $quote->fresh()->status);
        $this->assertSame(QuotationStageChangeRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertSame('La corrección no corresponde.', $request->fresh()->review_notes);
        $this->assertSame(1, OrderTrackerActivity::where('action', 'commercial_stage.revert_rejected')->count());
    }

    public function test_reject_rejects_an_already_processed_request(): void
    {
        [, $request, $admin] = $this->pendingRequest();
        $request->update(['status' => QuotationStageChangeRequest::STATUS_APPROVED]);

        $this->expectException(ValidationException::class);
        app(QuotationStageChangeService::class)->reject($request, $admin, 'No aplica.');
    }

    public function test_approve_rolls_back_quote_and_request_when_tracker_sync_fails(): void
    {
        [$quote, $request, $admin] = $this->pendingRequest();
        $tracker = Mockery::mock(OrderTrackerService::class);
        $tracker->shouldReceive('trackerForQuotation')->once()->andThrow(new RuntimeException('tracker failure'));

        try {
            $this->serviceWith($tracker)->approve($request, $admin);
            $this->fail('The tracker exception was not propagated.');
        } catch (RuntimeException) {
            $this->assertSame(QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, $quote->fresh()->status);
            $this->assertSame(QuotationStageChangeRequest::STATUS_PENDING, $request->fresh()->status);
        }
    }

    public function test_reject_rolls_back_request_when_activity_recording_fails(): void
    {
        [, $request, $admin] = $this->pendingRequest();
        $tracker = Mockery::mock(OrderTrackerService::class);
        $tracker->shouldReceive('trackerForQuotation')->once()->andThrow(new RuntimeException('activity failure'));

        try {
            $this->serviceWith($tracker)->reject($request, $admin, 'No aplica.');
            $this->fail('The activity exception was not propagated.');
        } catch (RuntimeException) {
            $this->assertSame(QuotationStageChangeRequest::STATUS_PENDING, $request->fresh()->status);
        }
    }

    public function test_pending_query_returns_only_pending_requests_in_newest_first_order(): void
    {
        [$quote, $first] = $this->pendingRequest();
        $first->update(['created_at' => now()->subMinute()]);
        $second = QuotationStageChangeRequest::create(array_merge($first->only([
            'quotation_request_id', 'requested_by', 'from_status', 'to_status', 'from_stage', 'to_stage', 'reason', 'warning',
        ]), ['status' => QuotationStageChangeRequest::STATUS_PENDING]));
        QuotationStageChangeRequest::create(array_merge($second->only([
            'quotation_request_id', 'requested_by', 'from_status', 'to_status', 'from_stage', 'to_stage', 'reason', 'warning',
        ]), ['status' => QuotationStageChangeRequest::STATUS_REJECTED]));

        $results = app(ListPendingQuotationStageChanges::class)->handle();

        $this->assertSame([$second->id, $first->id], $results->pluck('id')->all());
        $this->assertTrue($results->every->relationLoaded('quotationRequest'));
        $this->assertSame($quote->id, $results->first()->quotation_request_id);
    }

    public function test_domain_service_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(QuotationStageChangeService::class, app(QuotationStageChangeService::class));
    }

    /** @return array{QuotationRequest, QuotationStageChangeRequest, User} */
    private function pendingRequest(): array
    {
        $quote = $this->quote();
        $requester = User::factory()->create();
        $admin = User::factory()->create();
        $request = QuotationStageChangeRequest::create([
            'quotation_request_id' => $quote->id,
            'requested_by' => $requester->id,
            'from_status' => $quote->status,
            'to_status' => $this->transition()['target_status'],
            'from_stage' => 'seleccion_proveedor',
            'to_stage' => $this->transition()['target_stage'],
            'status' => QuotationStageChangeRequest::STATUS_PENDING,
            'reason' => 'Motivo suficientemente detallado.',
        ]);

        return [$quote, $request, $admin];
    }

    private function serviceWith(OrderTrackerService $tracker): QuotationStageChangeService
    {
        return new QuotationStageChangeService(app(SalesWorkflowService::class), $tracker);
    }

    private function quote(): QuotationRequest
    {
        return QuotationRequest::create([
            'reference' => 'SC-STAGE-'.str()->random(6),
            'date' => today(),
            'quote_to_name' => 'Cliente de prueba',
            'requested_by' => 'Ventas',
            'department' => 'ventas',
            'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            'commercial_phase' => 'seleccion_proveedor',
            'subtotal' => 100,
            'total_amount' => 115,
        ]);
    }

    private function transition(): array
    {
        return [
            'target_status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            'target_stage' => 'supplier_response',
            'warning' => 'Las respuestas se conservarán.',
        ];
    }
}
