<?php

namespace Tests\Feature;

use App\Domain\Ventas\OfertasCliente\Actions\ApproveClientOfferReview;
use App\Domain\Ventas\OfertasCliente\Actions\RejectClientOfferReview;
use App\Domain\Ventas\OfertasCliente\Support\ClientOfferReviewActivity;
use App\Models\ClientOfferReview;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Support\Concurrency\MonitoredOperation;
use ArrayObject;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ClientOfferReviewLockOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_locks_quotation_before_review(): void
    {
        [$quotation, $review, $admin] = $this->pendingReview();
        $queries = $this->captureQueries();
        $activity = Mockery::mock(ClientOfferReviewActivity::class);
        $activity->shouldReceive('recordReviewProcessed')->once();

        $action = new ApproveClientOfferReview($activity, app(MonitoredOperation::class));
        $result = $action->execute($review, $admin);

        $this->assertSame(ClientOfferReview::STATUS_APPROVED, $result->status);
        $this->assertAggregateLockOrder($queries, $quotation->id, $review->id);
    }

    public function test_rejection_locks_quotation_before_review(): void
    {
        [$quotation, $review, $admin] = $this->pendingReview();
        $queries = $this->captureQueries();
        $activity = Mockery::mock(ClientOfferReviewActivity::class);
        $activity->shouldReceive('recordReviewProcessed')->once();

        $action = new RejectClientOfferReview($activity, app(MonitoredOperation::class));
        $result = $action->execute($review, $admin, 'Costo fuera del presupuesto');

        $this->assertSame(ClientOfferReview::STATUS_REJECTED, $result->status);
        $this->assertAggregateLockOrder($queries, $quotation->id, $review->id);
    }

    /** @return array{QuotationRequest, ClientOfferReview, User} */
    private function pendingReview(): array
    {
        $quotation = QuotationRequest::factory()->create([
            'status' => QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
        ]);
        $persistedAdmin = User::factory()->create();
        $admin = Mockery::mock($persistedAdmin)->makePartial();
        $admin->shouldReceive('can')->andReturnTrue();
        $review = ClientOfferReview::create([
            'quotation_request_id' => $quotation->id,
            'requested_by' => $persistedAdmin->id,
            'status' => ClientOfferReview::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        return [$quotation, $review, $admin];
    }

    /** @return ArrayObject<int, array{sql: string, bindings: array}> */
    private function captureQueries(): ArrayObject
    {
        $queries = new ArrayObject;
        DB::listen(function (QueryExecuted $query) use ($queries): void {
            $queries->append(['sql' => strtolower($query->sql), 'bindings' => $query->bindings]);
        });

        return $queries;
    }

    private function assertAggregateLockOrder(iterable $queries, int $quotationId, int $reviewId): void
    {
        $quotationPosition = null;
        $reviewPosition = null;

        foreach ($queries as $position => $query) {
            if ($quotationPosition === null
                && str_contains($query['sql'], 'from `quotation_requests`')
                && in_array($quotationId, $query['bindings'], true)) {
                $quotationPosition = $position;
            }

            if ($reviewPosition === null
                && str_contains($query['sql'], 'from `client_offer_reviews`')
                && in_array($reviewId, $query['bindings'], true)) {
                $reviewPosition = $position;
            }
        }

        $this->assertNotNull($quotationPosition, 'No se observo el lock de la cotizacion.');
        $this->assertNotNull($reviewPosition, 'No se observo el lock de la revision.');
        $this->assertLessThan($reviewPosition, $quotationPosition, 'La cotizacion debe bloquearse antes que la revision.');
    }
}
