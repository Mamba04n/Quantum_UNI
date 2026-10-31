<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\PersistsSupplierBidResponses;
use App\Services\SupplierBidResponseService;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierResponseSaveSubmissionTest extends TestCase
{
    public function test_save_submits_hydrated_rows_without_client_dirty_tracking(): void
    {
        $rows = [11 => ['unit_cost' => '125.50', 'warranty' => '6 meses', 'delivery_time' => '5 dias', 'response_item_id' => 7]];
        $extra = ['extra_11_1' => ['quotation_request_item_id' => 11, 'unit_cost' => '25']];
        Livewire::test(SupplierResponseSubmissionHarness::class)
            ->set('supplierBidItems', $rows)
            ->set('supplierBidExtraItems', $extra)
            ->call('saveAllSupplierResponseItems')
            ->assertSet('submittedIds', [11, 'extra_11_1'])
            ->assertSet('submittedRows', $rows);
    }
}

class SupplierResponseSubmissionHarness extends Component
{
    use PersistsSupplierBidResponses;

    public array $supplierBidItems = [];
    public array $supplierBidExtraItems = [];
    public array $submittedIds = [];
    public array $submittedRows = [];

    public function saveSupplierResponseDraft(array $itemIds, SupplierBidResponseService $bidService): void
    {
        $this->submittedIds = $itemIds;
        $this->submittedRows = $this->supplierBidItems;
    }

    public function render()
    {
        return '<div></div>';
    }
}
