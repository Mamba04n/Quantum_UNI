<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Resolve users.role deletion
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        // 2. Normalize and check Polymorphic sources for tracking
        
        $tablesWithSource = [
            'order_tracker_references',
            'order_tracker_activities',
            'order_tracker_attachments'
        ];

        foreach($tablesWithSource as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'source_module') && Schema::hasColumn($table, 'source_id')) {
                // Normalize names
                DB::statement("UPDATE {$table} SET source_module = 'quotation_request' WHERE source_module IN ('quotation_requests', 'cotizacion', 'App\\\\Models\\\\QuotationRequest')");
                DB::statement("UPDATE {$table} SET source_module = 'supplier_rfq' WHERE source_module IN ('supplier_rfqs', 'rfq', 'App\\\\Models\\\\SupplierRfqDocument')");
                DB::statement("UPDATE {$table} SET source_module = 'supplier_bid_response' WHERE source_module IN ('supplier_bid_responses', 'bid_response', 'App\\\\Models\\\\SupplierBidResponse')");
                DB::statement("UPDATE {$table} SET source_module = 'supplier_bid_response_item' WHERE source_module IN ('supplier_bid_response_items', 'bid_item', 'App\\\\Models\\\\SupplierBidResponseItem')");
                
                // Now handle broken references (set source_id to NULL if record doesn't exist, keeping historical log but detached)
                // We keep source_module so we know what IT WAS.
                $modules = [
                    'quotation_request' => 'quotation_requests',
                    'supplier_rfq' => 'supplier_rfq_documents',
                    'supplier_bid_response' => 'supplier_bid_responses',
                    'supplier_bid_response_item' => 'supplier_bid_response_items'
                ];

                foreach($modules as $mod => $targetTable) {
                    if (Schema::hasTable($targetTable)) {
                        DB::statement("
                            UPDATE {$table} 
                            SET source_id = NULL 
                            WHERE source_module = '{$mod}' 
                            AND source_id IS NOT NULL 
                            AND source_id NOT IN (SELECT id FROM {$targetTable})
                        ");
                    }
                }
            }
        }
    }

    public function down(): void
    {
    }
};
