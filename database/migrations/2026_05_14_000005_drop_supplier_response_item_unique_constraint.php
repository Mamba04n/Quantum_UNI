<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_bid_response_items')) {
            return;
        }

        $exists = collect(DB::select("SHOW INDEX FROM supplier_bid_response_items WHERE Key_name = 'supplier_response_item_unique'"))->isNotEmpty();

        if ($exists) {
            $responseIndexExists = collect(DB::select("SHOW INDEX FROM supplier_bid_response_items WHERE Key_name = 'sbri_response_idx'"))->isNotEmpty();
            $itemIndexExists = collect(DB::select("SHOW INDEX FROM supplier_bid_response_items WHERE Key_name = 'sbri_quote_item_idx'"))->isNotEmpty();

            if (! $responseIndexExists) {
                DB::statement('ALTER TABLE supplier_bid_response_items ADD INDEX sbri_response_idx (supplier_bid_response_id)');
            }

            if (! $itemIndexExists) {
                DB::statement('ALTER TABLE supplier_bid_response_items ADD INDEX sbri_quote_item_idx (quotation_request_item_id)');
            }

            DB::statement('ALTER TABLE supplier_bid_response_items DROP INDEX supplier_response_item_unique');
        }
    }

    public function down(): void
    {
        //
    }
};
