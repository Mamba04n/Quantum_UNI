<?php
namespace Tests\Feature;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseTracker;
use App\Models\{ActivityLog, Entity, QuotationRequest, SupplierBidResponseItem, SupplierRfqDocument, User};
use App\Services\{PreliquidationCalculator, SaveSupplierPreliquidation};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreliquidationPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated in-memory connection: never migrate or truncate the ERP database.
        config(['database.default' => 'preliq_test', 'database.connections.preliq_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $tables = [
            'quotation_requests' => ['reference', 'status', 'commercial_phase'],
            'quotation_request_items' => ['quotation_request_id', 'part_number', 'part_name', 'quantity', 'selected_supplier_id'],
            'entities' => ['name', 'type', 'status'],
            'supplier_rfq_documents' => ['quotation_request_id', 'supplier_id', 'status', 'selected_item_ids', 'rfq_item_quantities'],
            'supplier_bids' => ['quotation_request_id', 'supplier_id', 'reference', 'status', 'currency', 'created_by'],
            'supplier_bid_responses' => ['quotation_request_id', 'supplier_id', 'supplier_bid_id', 'response_code', 'created_by', 'status', 'currency', 'preliquidation', 'notes'],
            'supplier_bid_response_items' => ['supplier_bid_response_id', 'preliquidation_key', 'quotation_request_item_id', 'customer_request_item_id', 'quoted_part_number', 'quoted_description', 'quoted_quantity', 'unit_cost', 'currency', 'item_status', 'warranty', 'delivery_time', 'internal_comment'],
            'supplier_bid_selections' => ['supplier_bid_response_item_id'],
            'activity_logs' => ['user_id', 'module', 'action', 'description', 'metadata', 'ip_address'],
            'products' => ['name', 'sku', 'part_number', 'brand', 'model', 'unit', 'stock_on_hand'],
        ];
        foreach ($tables as $name => $columns) Schema::create($name, function (Blueprint $table) use ($columns) {
            $table->id(); foreach ($columns as $column) $table->text($column)->nullable(); $table->timestamps();
        });
        Schema::table('quotation_requests', fn (Blueprint $table) => $table->unique('reference'));
        Schema::table('supplier_bids', fn (Blueprint $table) => $table->unique('reference'));
        $this->mock(SupplierBidResponseTracker::class, function ($mock) {
            $mock->shouldReceive('markRequestPendingResponse')->andReturnNull();
            $mock->shouldReceive('trackerFor')->andReturnNull();
        });
        DB::table('quotation_requests')->insert(['id' => 1, 'reference' => 'SC-TEST', 'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING]);
        foreach ([1, 2] as $id) {
            DB::table('entities')->insert(['id' => $id, 'name' => "Supplier $id", 'type' => 'proveedor', 'status' => 'activo']);
            DB::table('supplier_rfq_documents')->insert(['quotation_request_id' => 1, 'supplier_id' => $id, 'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER]);
        }
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Reviewer']));
    }

    private function row(): array
    {
        return ['quoted_part_number' => '332/A2428', 'quoted_description' => 'SEAL DUST', 'quoted_quantity' => 2,
            'unit_cost' => 3.46, 'weight_lb' => .07, 'assigned_usd' => 10, 'assigned_nio' => 370];
    }

    public function test_save_action_clears_previous_advance_error_and_reloads_saved_rows_without_loading_tracker_history(): void
    {
        Schema::table('supplier_bid_responses', function (Blueprint $table) {
            foreach (['contact_name', 'payment_terms', 'general_delivery_time', 'general_warranty', 'valid_until'] as $column) {
                $table->text($column)->nullable();
            }
        });
        DB::table('quotation_request_items')->insert(['id' => 11, 'quotation_request_id' => 1, 'part_number' => '332/A2428', 'quantity' => 2]);
        $this->mock(\App\Services\OrderTrackerService::class, function ($mock) {
            $mock->shouldNotReceive('trackerForQuotation');
        });
        $component = \Livewire\Livewire::test(PreliquidationSaveHarness::class)
            ->set('preliquidation', PreliquidationCalculator::defaults())
            ->set('preliquidation.exchange', 37)
            ->set('supplierBidItems', [11 => $this->row() + ['item_status' => 'quoted']])
            ->set('advanceStageError', 'Registra al menos una respuesta proveedor valida.')
            ->call('saveAllSupplierResponseItems')
            ->assertHasNoErrors()
            ->assertSet('advanceStageError', null)
            ->assertDispatched('preliquidation-saved');
        $saved = SupplierBidResponseItem::sole();
        $component->assertSet('supplierBidItems.11.response_item_id', $saved->id);
        $this->assertEquals(11, $saved->quotation_request_item_id);
        $this->assertTrue($saved->is_selectable);
        $coverage = app(\App\Domain\Ventas\LicitacionesProveedor\Queries\MeasureSupplierBidCoverage::class)->execute(QuotationRequest::findOrFail(1));
        $this->assertEquals(1, $coverage['responded']);
    }

    public function test_invalid_save_keeps_pending_data_and_does_not_report_success(): void
    {
        \Livewire\Livewire::test(PreliquidationSaveHarness::class)
            ->set('preliquidation', PreliquidationCalculator::defaults())
            ->set('preliquidation.exchange', 37)
            ->set('supplierBidExtraItems', ['new_line' => array_replace($this->row(), ['quoted_description' => '', 'item_status' => 'quoted'])])
            ->set('advanceStageError', 'Error anterior de avance')
            ->call('saveAllSupplierResponseItems')
            ->assertHasErrors('supplierBidExtraItems.new_line.quoted_description')
            ->assertSet('supplierBidExtraItems.new_line.unit_cost', 3.46)
            ->assertSet('advanceStageError', 'Error anterior de avance')
            ->assertNotDispatched('preliquidation-saved');
        $this->assertEquals(0, SupplierBidResponseItem::count());
    }

    public function test_save_survives_a_legacy_bid_label_from_a_renumbered_request_and_reuses_its_own_bid(): void
    {
        DB::table('quotation_requests')->insert(['id' => 2, 'reference' => 'SC-RENAMED', 'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING]);
        DB::table('supplier_bids')->insert(['quotation_request_id' => 2, 'supplier_id' => 1, 'reference' => 'BID-SC-TEST-001']);
        [$response, $mapping] = $this->save(1, ['line' => $this->row()]);
        $firstBid = $response->supplier_bid_id;
        $this->assertNotNull($firstBid);
        $this->assertEquals('BID-SC-TEST-Q1-S1', \App\Models\SupplierBid::findOrFail($firstBid)->reference);
        $this->save(1, ['line' => $this->row() + ['response_item_id' => $mapping['line']]], $response, SaveSupplierPreliquidation::revision($response->fresh()));
        $this->assertEquals($firstBid, $response->fresh()->supplier_bid_id);
        $this->assertEquals(2, \App\Models\SupplierBid::count());
    }

    public function test_legacy_bid_entry_and_preliquidation_share_one_bid_even_after_renumbering(): void
    {
        Schema::table('supplier_bids', function (Blueprint $table) {
            foreach (['exchange_rate', 'payment_terms', 'delivery_time', 'warranty', 'valid_until', 'proforma_path', 'notes'] as $column) $table->text($column)->nullable();
        });
        Schema::create('supplier_bid_items', function (Blueprint $table) {
            $table->id();
            foreach (['supplier_bid_id', 'quotation_request_item_id', 'quantity', 'unit_cost', 'line_total', 'currency', 'is_quoted', 'delivery_time', 'estimated_arrival_date', 'part_model', 'serial_number', 'brand', 'warranty', 'notes'] as $column) $table->text($column)->nullable();
            $table->timestamps();
        });
        DB::table('quotation_request_items')->insert(['id' => 11, 'quotation_request_id' => 1, 'part_number' => 'PART', 'quantity' => 2]);
        DB::table('supplier_bids')->insert(['quotation_request_id' => 2, 'supplier_id' => 2, 'reference' => 'SB-SC-TEST-01']);
        $tracker = \Mockery::mock(\App\Models\OrderTracker::class)->makePartial();
        $tracker->shouldReceive('update')->andReturn(true);
        $this->mock(\App\Services\OrderTrackerService::class, function ($mock) use ($tracker) {
            $mock->shouldReceive('trackerForQuotation')->andReturn($tracker);
            $mock->shouldReceive('addReference')->andReturnNull();
            $mock->shouldReceive('recordActivity')->andReturn(new \App\Models\OrderTrackerActivity);
        });
        $service = app(\App\Domain\Ventas\LicitacionesProveedor\Services\SupplierBidService::class);
        $data = ['supplier_id' => 1, 'items' => [11 => ['quantity' => 2, 'unit_cost' => 10]]];
        $bid = $service->createOrUpdateBid(QuotationRequest::findOrFail(1), $data, auth()->user());
        $this->assertEquals('BID-SC-TEST-Q1-S1', $bid->reference);
        DB::table('quotation_requests')->where('id', 1)->update(['reference' => 'SC-RENAMED']);
        $repository = app(\App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository::class);
        $reused = $repository->bidForSupplier(QuotationRequest::findOrFail(1), Entity::findOrFail(1), auth()->user());
        $this->assertEquals($bid->id, $reused->id);
        $this->assertEquals($bid->reference, $reused->reference, 'Historical bid labels remain intact');
        $again = $service->createOrUpdateBid(QuotationRequest::findOrFail(1), $data, auth()->user());
        $this->assertEquals($bid->id, $again->id);
        $this->assertEquals(1, $again->items->count());
        $this->assertEquals(2, \App\Models\SupplierBid::count());
    }

    public function test_new_supplier_bid_does_not_reuse_a_deleted_sequence_number(): void
    {
        DB::table('supplier_bids')->insert(['quotation_request_id' => 1, 'supplier_id' => 2, 'reference' => 'BID-SC-TEST-002']);
        [$response] = $this->save(1, ['line' => $this->row()]);
        $this->assertCount(1, $response->fresh()->items);
        $this->assertEquals('BID-SC-TEST-002', \App\Models\SupplierBid::where('supplier_id', 2)->value('reference'));
    }

    private function save(int $supplier, array $rows, $response = null, ?string $revision = null, float $freight = 3): array
    {
        return app(SaveSupplierPreliquidation::class)->save(QuotationRequest::findOrFail(1), Entity::findOrFail($supplier), $response?->id,
            array_replace(PreliquidationCalculator::defaults(), ['freight' => $freight, 'exchange' => 37]), $rows, ['notes' => "Supplier $supplier notes"], auth()->user(), $revision);
    }

    public function test_batch_save_reload_update_delete_and_supplier_isolation(): void
    {
        [$a, $map] = $this->save(1, ['new_a' => $this->row(), 'new_b' => $this->row()]);
        [$b] = $this->save(2, ['new_c' => $this->row()], freight: 1);
        $this->assertCount(2, $a->fresh()->items);
        $this->assertEquals(.07, $a->fresh()->preliquidation['weights'][$map['new_a']]);
        $this->assertEquals(370, $a->fresh()->preliquidation['assigned'][$map['new_a']]['nio']);
        $bBefore = SaveSupplierPreliquidation::revision($b->fresh());
        $changed = array_replace($this->row(), ['response_item_id' => $map['new_a'], 'unit_cost' => 4]);
        [$a] = $this->save(1, ['new_a' => $changed], $a, SaveSupplierPreliquidation::revision($a->fresh()), 2.75);
        $this->assertCount(1, $a->fresh()->items);
        $this->assertEquals(4, $a->fresh()->items->first()->unit_cost);
        $this->assertSame($bBefore, SaveSupplierPreliquidation::revision($b->fresh()));
        $log = ActivityLog::where('action', 'preliquidation.saved')->latest('id')->first();
        $this->assertCount(2, $log->metadata['before']['items']);
        $this->assertCount(1, $log->metadata['after']['items']);
        $this->assertEquals(1, $log->user_id);
        [$a] = $this->save(1, [], $a, SaveSupplierPreliquidation::revision($a->fresh()));
        $this->assertCount(0, $a->fresh()->items, 'The last row can also be deleted');
        $this->assertSame($bBefore, SaveSupplierPreliquidation::revision($b->fresh()));
    }

    public function test_stale_revision_cannot_overwrite_same_second_changes(): void
    {
        [$a, $map] = $this->save(1, ['new_a' => $this->row()]);
        $revision = SaveSupplierPreliquidation::revision($a->fresh());
        SupplierBidResponseItem::whereKey($map['new_a'])->update(['unit_cost' => 99]);
        try {
            $this->save(1, ['new_a' => $this->row() + ['response_item_id' => $map['new_a']]], $a, $revision);
            $this->fail('Expected stale edit rejection');
        } catch (ValidationException $e) {
            $this->assertEquals(99, SupplierBidResponseItem::find($map['new_a'])->unit_cost);
            $this->assertCount(1, ActivityLog::all());
        }
    }

    public function test_one_hundred_new_rows_are_inserted_as_a_batch(): void
    {
        DB::enableQueryLog();
        [$response] = $this->save(1, array_fill(0, 100, $this->row() + ['quotation_request_item_id' => null]));
        $inserts = collect(DB::getQueryLog())->filter(fn ($entry) => str_starts_with(strtolower($entry['query']), 'insert into "supplier_bid_response_items"'));
        DB::disableQueryLog();
        $this->assertCount(1, $inserts);
        $this->assertCount(100, $response->fresh()->items);
    }

    public function test_form_reload_and_supplier_switch_restore_only_their_own_rows_and_settings(): void
    {
        [$a] = $this->save(1, ['a' => $this->row(), 'b' => $this->row()]);
        [$b] = $this->save(2, ['c' => $this->row()], freight: 1);
        DB::table('quotation_request_items')->insert(['id' => 99, 'quotation_request_id' => 1, 'part_number' => 'UNRELATED']);
        $form = new PreliquidationFormHarness;
        $form->selectedQuoteId = 1;
        $form->editSupplierResponse($a->id);
        $this->assertCount(0, $form->supplierBidItems, 'No phantom rows copied from the request');
        $this->assertCount(2, $form->supplierBidExtraItems);
        $this->assertEquals(3, $form->preliquidation['freight']);
        $form->supplierItemProformas = ['pending' => ['file']];
        $form->editSupplierResponse($b->id);
        $this->assertCount(1, $form->supplierBidExtraItems);
        $this->assertEquals(1, $form->preliquidation['freight']);
        $this->assertSame('Supplier 2 notes', $form->supplierNotes);
        $this->assertSame([], $form->supplierItemProformas);
        $this->assertSame(SaveSupplierPreliquidation::revision($b->fresh()), $form->preliquidationRevision);
        $form->preliquidation['iva'] = 12;
        $form->updatingPreliquidation(false, 'taxed');
        $form->updatedPreliquidation(false, 'taxed');
        $this->assertEquals(0, $form->preliquidation['iva']);
        $form->updatedPreliquidation(true, 'taxed');
        $this->assertEquals(12, $form->preliquidation['iva'], 'Restore this supplier tax rates, not global defaults');
    }

    public function test_foreign_supplier_item_cannot_be_overwritten_and_transaction_rolls_back(): void
    {
        [$a, $mapA] = $this->save(1, ['a' => $this->row()]);
        [$b, $mapB] = $this->save(2, ['b' => $this->row()]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $this->save(1, ['a' => $this->row() + ['response_item_id' => $mapB['b']]], $a, SaveSupplierPreliquidation::revision($a->fresh()));
        } finally {
            $this->assertCount(2, SupplierBidResponseItem::all());
            $this->assertEquals(3.46, SupplierBidResponseItem::find($mapA['a'])->unit_cost);
        }
    }

    public function test_newly_added_item_assigned_to_supplier_is_loaded_into_preliquidation_items(): void
    {
        [$a, $map] = $this->save(1, ['existing' => $this->row()]);
        
        DB::table('quotation_request_items')->insert([
            'id' => 101,
            'quotation_request_id' => 1,
            'part_number' => 'NEW-PART',
            'part_name' => 'Nueva Pieza Asignada',
            'quantity' => 3,
            'selected_supplier_id' => 1,
        ]);

        $form = new PreliquidationFormHarness;
        $form->selectedQuoteId = 1;
        $form->editSupplierResponse($a->id);

        $this->assertArrayHasKey(101, $form->supplierBidItems);
        $this->assertEquals('NEW-PART', $form->supplierBidItems[101]['quoted_part_number']);
        $this->assertEquals('Nueva Pieza Asignada', $form->supplierBidItems[101]['quoted_description']);
        $this->assertEquals(3, $form->supplierBidItems[101]['quoted_quantity']);
        $this->assertNull($form->supplierBidItems[101]['response_item_id']);
    }

    public function test_solicitud_item_quantity_is_well_defined_and_modifiable(): void
    {
        DB::table('quotation_request_items')->insert([
            'id' => 102,
            'quotation_request_id' => 1,
            'part_number' => 'SOL-102',
            'part_name' => 'Item con cantidad de solicitud',
            'quantity' => 5,
            'selected_supplier_id' => 1,
        ]);

        $form = new PreliquidationFormHarness;
        $form->selectedQuoteId = 1;
        $form->selectedSupplierId = '1';
        $form->initDefaults();

        $this->assertArrayHasKey(102, $form->supplierBidItems);
        $this->assertEquals(5, $form->supplierBidItems[102]['quoted_quantity'], 'Quantity is defined from solicitud initially');

        // Supplier quotes 10 instead of 5
        $form->supplierBidItems[102]['quoted_quantity'] = 10;
        $form->supplierBidItems[102]['unit_cost'] = 12.5;

        [$response, $map] = $this->save(1, [
            102 => $form->supplierBidItems[102],
        ]);

        $savedItem = SupplierBidResponseItem::find($map[102]);
        $this->assertEquals(10, $savedItem->quoted_quantity, 'Saved quoted quantity reflects modified amount');

        $originalItem = DB::table('quotation_request_items')->where('id', 102)->first();
        $this->assertEquals(5, $originalItem->quantity, 'Original solicitud requested quantity remains intact');
    }

    public function test_bulk_parser_and_preliquidation_supports_unit_price_and_assigned_price(): void
    {
        // 1. BulkItemParserService extracts Unit Price header
        $parser = new \App\Domain\Ventas\Cotizaciones\Services\BulkItemParserService;
        $csv = "Part number,Description,Qty,Unit Price,Weight Lb\n332/A2428,SEAL DUST,2,3.46,0.07\n332/A2429,PACKING,2,11.53,0.11";
        $parsed = $parser->parse($csv);
        $this->assertCount(2, $parsed);
        $this->assertEquals(3.46, $parsed[0]['supplier_unit_cost']);
        $this->assertEquals(11.53, $parsed[1]['supplier_unit_cost']);

        // 2. Preliquidation calculates EXW, DDP, Price and Assigned Price
        $calc = new PreliquidationCalculator;
        $settings = array_replace(PreliquidationCalculator::defaults(), [
            'handling' => 0, 'freight' => 3, 'commission' => 4, 'financing' => 0,
            'iva' => 15, 'dai' => 5, 'isc' => 5, 'customs' => 0, 'local_transport' => 0,
            'margin' => 30, 'exchange' => 37,
        ]);
        $rows = [
            'item_1' => [
                'quoted_part_number' => '332/A2428', 'quoted_description' => 'SEAL DUST',
                'quoted_quantity' => 2, 'unit_cost' => 3.46, 'weight_lb' => 0.07,
                'assigned_usd' => 19.10,
            ],
        ];
        $result = $calc->calculate($settings, $rows);
        $rowResult = $result['rows']['item_1'];

        $this->assertEquals(6.92, $rowResult['subtotal_exw']);
        $this->assertEquals(3.67, $rowResult['cif']);
        $this->assertEqualsWithDelta(4.73, $rowResult['ddp'], 0.01);
        $this->assertEqualsWithDelta(6.76, $rowResult['suggested_price'], 0.01);
        $this->assertEquals(19.10, $rowResult['price']);
        $this->assertEquals(19.10, $rowResult['assigned_unit']);
        $this->assertEquals(38.20, $rowResult['assigned_total']);
    }

    public function test_exact_client_price_in_either_currency_survives_save_and_reload(): void
    {
        Schema::table('supplier_bid_response_items', function (Blueprint $table) {
            foreach (['preliquidated_unit_price', 'preliquidated_unit_price_nio', 'ddp_unit_cost', 'cif_unit_cost', 'margin_percentage'] as $column) {
                $table->decimal($column, 18, 2)->nullable();
            }
        });
        foreach (['assigned_nio' => 3500, 'assigned_usd' => 100] as $field => $amount) {
            $supplier = $field === 'assigned_nio' ? 1 : 2;
            $row = array_replace($this->row(), ['assigned_nio' => null, 'assigned_usd' => null, $field => $amount, 'quoted_quantity' => 12]);
            [$response, $map] = $this->save($supplier, ['a' => $row]);
            $item = SupplierBidResponseItem::findOrFail($map['a']);
            $expectedNio = $field === 'assigned_nio' ? 3500 : 3700;
            $this->assertEquals($expectedNio, $item->preliquidated_unit_price_nio);
            $this->assertEquals(round($expectedNio / 37, 2), $item->preliquidated_unit_price);
            $reloaded = app(PreliquidationCalculator::class)->forResponse($response->fresh());
            $this->assertEqualsWithDelta($expectedNio * 12, $reloaded['sales_nio'], 0.000001);
            $form = new PreliquidationFormHarness;
            $form->selectedQuoteId = 1;
            $form->editSupplierResponse($response->id);
            $this->assertEquals($amount, array_values($form->supplierBidExtraItems)[0][$field]);
        }
    }

    public function test_editing_final_price_replaces_previous_currency_and_clearing_restores_margin(): void
    {
        $form = new PreliquidationFormHarness;
        $form->preliquidation = array_replace(PreliquidationCalculator::defaults(), ['exchange' => 37]);
        $form->supplierBidExtraItems = [];
        $form->supplierBidItems = [1 => array_replace($this->row(), ['assigned_nio' => 3500])];
        $form->updatedSupplierBidItems(3500, '1.assigned_nio');
        $this->assertNull($form->supplierBidItems[1]['assigned_usd']);
        $this->assertEqualsWithDelta(7000, $form->preliquidationTotals()['sales_nio'], 0.000001);
        $form->supplierBidItems[1]['assigned_usd'] = 100;
        $form->updatedSupplierBidItems(100, '1.assigned_usd');
        $this->assertNull($form->supplierBidItems[1]['assigned_nio']);
        $this->assertEquals(200, $form->preliquidationTotals()['sales']);
        $form->supplierBidItems[1]['assigned_usd'] = '';
        $form->updatedSupplierBidItems('', '1.assigned_usd');
        $totals = $form->preliquidationTotals();
        $this->assertEquals($totals['rows'][1]['suggested_price'], $totals['rows'][1]['price']);
        $form->supplierBidExtraItems = ['new_a' => $this->row()];
        $form->updatedSupplierBidExtraItems(370, 'new_a.assigned_nio');
        $this->assertNull($form->supplierBidExtraItems['new_a']['assigned_usd']);
    }

    public function test_invalid_manual_client_price_is_rejected_before_saving(): void
    {
        foreach (['assigned_nio' => -3500, 'assigned_usd' => 'invalid'] as $field => $value) {
            try {
                $this->save(1, ['a' => array_replace($this->row(), [$field => $value])]);
                $this->fail('Expected invalid price rejection');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('rows.a.'.$field, $exception->errors());
                $this->assertEquals(0, SupplierBidResponseItem::count());
            }
        }
    }

    public function test_open_rfq_assignment_reports_inline_errors_and_confirms_success(): void
    {
        $open = SupplierRfqDocument::create(['quotation_request_id' => 1, 'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER]);
        $component = \Livewire\Livewire::test(OpenRfqAssignmentHarness::class)
            ->call('establishOpenRfqSupplier')
            ->assertHasErrors('responseSupplierName')
            ->assertNotDispatched('rfq-supplier-established');
        $this->assertNull($open->fresh()->supplier_id);
        $component->set('responseSupplierName', 'Supplier 1')
            ->call('establishOpenRfqSupplier')
            ->assertHasErrors('responseSupplierName')
            ->assertNotDispatched('rfq-supplier-established');
        $this->assertNull($open->fresh()->supplier_id);
        $component->set('responseSupplierName', 'New supplier')
            ->call('establishOpenRfqSupplier')
            ->assertHasNoErrors()
            ->assertDispatched('rfq-supplier-established');
        $supplierId = Entity::where('name', 'New supplier')->value('id');
        $this->assertEquals($supplierId, $open->fresh()->supplier_id);
        $component->assertSet('selectedSupplierId', (string) $supplierId);
    }

    public function test_update_cost_total_calculates_rate_per_pound_from_weight(): void
    {
        $form = new PreliquidationFormHarness;
        $form->selectedQuoteId = 1;
        $form->initDefaults();
        $form->supplierBidItems = [
            1 => [
                'quoted_part_number' => 'ABC-1', 'quoted_description' => 'Item 1',
                'quoted_quantity' => 2, 'unit_cost' => 50, 'weight_lb' => 10, // 20 lb total
                'item_status' => 'quoted',
            ],
            2 => [
                'quoted_part_number' => 'ABC-2', 'quoted_description' => 'Item 2',
                'quoted_quantity' => 1, 'unit_cost' => 30, 'weight_lb' => 30, // 30 lb total
                'item_status' => 'quoted',
            ],
        ];
        // Total weight = 20 + 30 = 50 lb.

        // Enter Freight total USD = 209.31
        $form->updateCostTotal('freight', 209.31);

        // Rate = 209.31 / 50 = 4.1862 USD/lb
        $this->assertEquals(4.1862, $form->preliquidation['freight']);
        $totals = $form->preliquidationTotals();
        $this->assertEqualsWithDelta(209.31, $totals['costs']['freight'], 0.01);
        $this->assertStringContainsString('Tarifa de Freight cost calculada: 4.1862 USD/lb', $form->preliquidationChange);

        // Reset to 0
        $form->updateCostTotal('freight', 0);
        $this->assertEquals(0, $form->preliquidation['freight']);

        // Test with 0 weight (item status not_quoted)
        $form->supplierBidItems[1]['item_status'] = 'not_quoted';
        $form->supplierBidItems[2]['item_status'] = 'not_quoted';
        $form->updateCostTotal('freight', 100);
        $this->assertArrayHasKey('preliquidation.freight', $form->errors);
    }
}

class OpenRfqAssignmentHarness extends \Livewire\Component
{
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\PersistsSupplierBidResponses;

    public string $responseSupplierName = '';
    public string $supplierContactName = '';
    public string $selectedSupplierId = '';

    public function supplierRfqDocumentForSelectedSupplier(): ?SupplierRfqDocument
    {
        return SupplierRfqDocument::where('quotation_request_id', 1)->whereNull('supplier_id')->first();
    }

    public function render() { return '<div></div>'; }
}

class PreliquidationFormHarness extends \stdClass
{
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidForm;
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesPreliquidation;

    public array $errors = [];
    public array $dispatched = [];

    public function initDefaults(): void { $this->loadSupplierBidDefaults(); }
    public function updatedSupplierCurrency(): void { $this->supplierCurrencyExchangeRate = '37'; }
    public function selectedQuote(): QuotationRequest { return QuotationRequest::with('items')->findOrFail($this->selectedQuoteId); }
    public function addError($key, $message): void { $this->errors[$key] = $message; }
    public function dispatch($event, ...$args): void { $this->dispatched[$event] = $args; }
}

class PreliquidationSaveHarness extends \Livewire\Component
{
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidForm;
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesPreliquidation;
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\PersistsSupplierBidResponses;

    public int $selectedQuoteId = 1;
    public string $selectedSupplierId = '1';
    public string $supplierCurrency = 'USD';
    public string $supplierCurrencyExchangeRate = '37';
    public string $supplierContactName = '';
    public string $supplierPaymentTerms = '';
    public string $supplierDeliveryTime = '';
    public string $supplierWarranty = '';
    public string $supplierValidUntil = '';
    public string $supplierNotes = '';
    public array $supplierBidItems = [];
    public array $supplierBidExtraItems = [];
    public array $supplierBidDeletedItemIds = [];
    public array $supplierBidItemVersions = [];
    public array $supplierProformas = [];
    public array $supplierItemProformas = [];
    public ?int $activeSupplierBidResponseId = null;
    public ?int $editingSupplierResponseId = null;
    public ?string $advanceStageError = null;
    public int $bidStep = 2;

    public function updatedSupplierCurrency(): void { $this->supplierCurrencyExchangeRate = '37'; }
    public function selectedQuote(): QuotationRequest { return QuotationRequest::with(['items', 'supplierRfqDocuments'])->findOrFail(1); }
    public function supplierRfqDocumentForSelectedSupplier(): SupplierRfqDocument { return SupplierRfqDocument::where('supplier_id', 1)->firstOrFail(); }
    public function supplierForResponse(SupplierRfqDocument $rfq): Entity { return Entity::findOrFail($rfq->supplier_id); }
    public function render() { return '<div></div>'; }
}
