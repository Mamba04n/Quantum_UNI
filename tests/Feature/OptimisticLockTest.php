<?php

namespace Tests\Feature;

use App\Models\QuotationRequest;
use App\Support\Concurrency\OptimisticLock;
use App\Support\Concurrency\StaleModelException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_editor_cannot_overwrite_a_newer_item_version(): void
    {
        $quotation = QuotationRequest::factory()->create();
        $item = $quotation->items()->create([
            'part_name' => 'Filtro de aceite',
            'description' => 'Filtro de aceite',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
            'status' => 'pending',
        ]);

        $firstEditor = $item->fresh();
        $secondEditor = $item->fresh();
        $lock = app(OptimisticLock::class);

        $updated = $lock->update($firstEditor, 1, ['unit_price' => 125, 'line_total' => 125]);

        $this->assertSame(2, $updated->version);
        $this->assertSame('125.00', $updated->unit_price);

        try {
            $lock->update($secondEditor, 1, ['unit_price' => 80, 'line_total' => 80]);
            $this->fail('El guardado obsoleto debio ser rechazado.');
        } catch (StaleModelException $exception) {
            $this->assertSame('quotation_request_items', $exception->resourceType);
            $this->assertSame($item->id, $exception->resourceId);
        }

        $this->assertSame('125.00', $item->fresh()->unit_price);
        $this->assertSame(2, $item->fresh()->version);
    }
}
