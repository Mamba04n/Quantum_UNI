<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the 9 legacy tables that returned due to dump restoration issues
        Schema::disableForeignKeyConstraints();

        $legacyTables = [
            'machines',
            'machine_parts',
            'maintenance_logs',
            'suppliers',
            'supplier_bank_accounts',
            'supplier_brands',
            'supplier_machine_types',
            'supplier_payment_methods',
            'supplier_product_types'
        ];

        foreach ($legacyTables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        // 2. Fix the 4 polymorphic references in order_tracker_activities and attachments
        if (Schema::hasTable('order_tracker_activities')) {
            DB::statement("UPDATE order_tracker_activities SET source_module = 'supplier_rfq_document', source_id = NULL WHERE source_module IN ('supplier_rfq_documents', 'supplier_rfq')");
        }

        if (Schema::hasTable('order_tracker_attachments')) {
            DB::statement("UPDATE order_tracker_attachments SET source_module = 'supplier_rfq_document', source_id = NULL WHERE source_module IN ('supplier_rfq_documents', 'supplier_rfq')");
        }

        // 3. Drop the 'Unknown' brand if it exists
        if (Schema::hasTable('brands')) {
            DB::statement("DELETE FROM brands WHERE name = 'Unknown'");
        }
    }

    public function down(): void
    {
        // No down migration
    }
};
