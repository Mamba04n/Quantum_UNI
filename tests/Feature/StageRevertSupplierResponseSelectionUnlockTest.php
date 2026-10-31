<?php

namespace Tests\Feature;

use App\Domain\Ventas\FlujoTrabajo\Services\QuotationStageChangeService;
use App\Domain\Ventas\LicitacionesProveedor\Actions\RecordSupplierBidResponseItem;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidActivity;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseData;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponsePayload;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseTracker;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBid;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\SaveSupplierPreliquidation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StageRevertSupplierResponseSelectionUnlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'revert_test',
            'database.connections.revert_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        $tables = [
            'quotation_requests' => ['reference', 'status', 'commercial_phase', 'client_offer_pdf_path', 'client_offer_generated_at', 'currency'],
            'quotation_request_items' => ['quotation_request_id', 'part_number', 'part_name', 'quantity', 'selected_supplier_id', 'unit_cost', 'unit_price', 'line_total', 'supplier_selection_status', 'is_approved'],
            'entities' => ['name', 'type', 'status'],
            'supplier_rfq_documents' => ['quotation_request_id', 'supplier_id', 'status', 'selected_item_ids', 'rfq_item_quantities'],
            'supplier_bids' => ['quotation_request_id', 'supplier_id', 'reference', 'status', 'currency', 'created_by'],
            'supplier_bid_responses' => ['quotation_request_id', 'supplier_id', 'supplier_bid_id', 'response_code', 'created_by', 'status', 'currency', 'preliquidation', 'notes'],
            'supplier_bid_response_items' => ['supplier_bid_response_id', 'preliquidation_key', 'quotation_request_item_id', 'customer_request_item_id', 'quoted_part_number', 'quoted_description', 'quoted_quantity', 'unit_cost', 'currency', 'item_status', 'warranty', 'delivery_time', 'internal_comment', 'quoted_brand', 'quoted_model'],
            'supplier_bid_selections' => ['quotation_request_id', 'quotation_request_item_id', 'supplier_bid_response_id', 'supplier_bid_response_item_id', 'supplier_id', 'selected_supplier_id', 'selected_quantity', 'selected_unit_cost'],
            'activity_logs' => ['user_id', 'module', 'action', 'description', 'metadata', 'ip_address'],
            'users' => ['name', 'email'],
            'purchase_orders' => ['quotation_request_id', 'status'],
            'order_trackers' => ['operational_tracking_code', 'operational_phase', 'operational_status', 'main_reference'],
            'order_tracker_references' => ['tracker_id', 'source_module', 'source_id', 'reference_type', 'reference_code'],
            'order_tracker_activities' => ['tracker_id', 'action', 'description', 'phase', 'source_module', 'source_id', 'old_status', 'new_status', 'user_id'],
            'order_tracker_attachments' => ['tracker_id', 'activity_id', 'source_module', 'source_id', 'file_path'],
            'order_tracker_comments' => ['tracker_id', 'activity_id', 'user_id', 'comment'],
            'approval_requests' => ['tracker_id', 'approver_id', 'status'],
        ];

        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
            });
        }

        $this->mock(SupplierBidResponseTracker::class, function ($mock) {
            $mock->shouldReceive('markRequestPendingResponse')->andReturnNull();
            $mock->shouldReceive('trackerFor')->andReturnNull();
            $mock->shouldReceive('syncTrackerToSupplier')->andReturnNull();
            $mock->shouldReceive('addResponseReference')->andReturnNull();
        });
    }

    public function test_reverting_to_supplier_response_clears_stale_selections(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $quote = QuotationRequest::create([
            'reference' => 'SC-001',
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'commercial_phase' => 'client_offer',
            'client_offer_pdf_path' => '/some/path.pdf',
        ]);
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'P-100',
            'quantity' => 10,
            'supplier_selection_status' => 'selected',
            'is_approved' => true,
        ]);
        $supplier = Entity::create(['name' => 'Supplier A', 'type' => 'proveedor', 'status' => 'activo']);
        $resp = SupplierBidResponse::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'response_code' => 'RESP-1',
            'status' => 'received',
        ]);
        $respItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $resp->id,
            'quotation_request_item_id' => $item->id,
            'quoted_part_number' => 'P-100',
            'quoted_description' => 'Desc',
            'quoted_quantity' => 10,
            'unit_cost' => 50,
            'item_status' => 'quoted',
        ]);
        SupplierBidSelection::create([
            'quotation_request_id' => $quote->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_id' => $resp->id,
            'supplier_bid_response_item_id' => $respItem->id,
            'supplier_id' => $supplier->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 10,
            'selected_unit_cost' => 50,
        ]);

        $this->assertSame(1, $quote->supplierBidSelections()->count());
        $this->assertTrue($respItem->selection()->exists());

        $service = app(QuotationStageChangeService::class);
        $service->executeRevert($quote, [
            'label' => 'Volver a Respuesta Proveedor',
            'target_status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            'target_stage' => 'supplier_response',
        ], $user);

        $quote->refresh();
        $this->assertSame(QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, $quote->status);
        $this->assertNull($quote->client_offer_pdf_path);
        $this->assertSame(0, $quote->supplierBidSelections()->count());
        $this->assertFalse($respItem->fresh()->selection()->exists());
        $this->assertNull($item->fresh()->supplier_selection_status);
        $this->assertFalse((bool) $item->fresh()->is_approved);
    }

    public function test_save_preliquidation_succeeds_when_quote_in_supplier_response_pending_even_with_selection(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $quote = QuotationRequest::create([
            'reference' => 'SC-002',
            'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            'commercial_phase' => 'supplier_bid',
        ]);
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'P-200',
            'quantity' => 5,
        ]);
        $supplier = Entity::create(['name' => 'Supplier B', 'type' => 'proveedor', 'status' => 'activo']);
        SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
        ]);
        $resp = SupplierBidResponse::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'response_code' => 'RESP-2',
            'status' => 'received',
        ]);
        $respItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $resp->id,
            'quotation_request_item_id' => $item->id,
            'quoted_part_number' => 'P-200',
            'quoted_description' => 'Test Desc',
            'quoted_quantity' => 5,
            'unit_cost' => 100,
            'item_status' => 'quoted',
        ]);
        // Simulate a stale selection from a previous run
        SupplierBidSelection::create([
            'quotation_request_id' => $quote->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_id' => $resp->id,
            'supplier_bid_response_item_id' => $respItem->id,
            'supplier_id' => $supplier->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 5,
            'selected_unit_cost' => 100,
        ]);

        $saver = app(SaveSupplierPreliquidation::class);
        $settings = ['exchange' => 37, 'margin' => 20, 'handling' => 0, 'freight' => 0, 'customs' => 0, 'transport' => 0, 'da' => 0, 'isc' => 0];
        $rows = [
            $item->id => [
                'response_item_id' => $respItem->id,
                'quotation_request_item_id' => $item->id,
                'quoted_part_number' => 'P-200',
                'quoted_description' => 'Test Desc',
                'quoted_quantity' => 5,
                'unit_cost' => 120, // Updated cost
                'weight_lb' => 10,
                'item_status' => 'quoted',
            ],
        ];

        [$savedResp, $mapping] = $saver->save(
            $quote,
            $supplier,
            $resp->id,
            $settings,
            $rows,
            ['notes' => 'Updated notes'],
            $user,
            SaveSupplierPreliquidation::revision($resp->fresh())
        );

        $this->assertNotNull($savedResp);
        $this->assertSame(0, $quote->supplierBidSelections()->count());
        $this->assertSame(120.0, (float) $respItem->fresh()->unit_cost);
    }

    public function test_save_preliquidation_still_blocks_when_quote_in_active_client_offer(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
        $quote = QuotationRequest::create([
            'reference' => 'SC-003',
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'commercial_phase' => 'client_offer',
        ]);
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'P-300',
            'quantity' => 2,
        ]);
        $supplier = Entity::create(['name' => 'Supplier C', 'type' => 'proveedor', 'status' => 'activo']);
        SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
        ]);
        $resp = SupplierBidResponse::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'response_code' => 'RESP-3',
            'status' => 'received',
        ]);
        $respItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $resp->id,
            'quotation_request_item_id' => $item->id,
            'quoted_part_number' => 'P-300',
            'quoted_description' => 'Test Desc',
            'quoted_quantity' => 2,
            'unit_cost' => 80,
            'item_status' => 'quoted',
        ]);
        SupplierBidSelection::create([
            'quotation_request_id' => $quote->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_id' => $resp->id,
            'supplier_bid_response_item_id' => $respItem->id,
            'supplier_id' => $supplier->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 2,
            'selected_unit_cost' => 80,
        ]);

        $saver = app(SaveSupplierPreliquidation::class);
        $settings = ['exchange' => 37, 'margin' => 20, 'handling' => 0, 'freight' => 0, 'customs' => 0, 'transport' => 0, 'da' => 0, 'isc' => 0];
        $rows = [
            $item->id => [
                'response_item_id' => $respItem->id,
                'quotation_request_item_id' => $item->id,
                'quoted_part_number' => 'P-300',
                'quoted_description' => 'Test Desc',
                'quoted_quantity' => 2,
                'unit_cost' => 90,
                'weight_lb' => 5,
                'item_status' => 'quoted',
            ],
        ];

        $saver->save(
            $quote,
            $supplier,
            $resp->id,
            $settings,
            $rows,
            ['notes' => 'Attempt edit while offer active'],
            $user,
            SaveSupplierPreliquidation::revision($resp)
        );
    }
}
