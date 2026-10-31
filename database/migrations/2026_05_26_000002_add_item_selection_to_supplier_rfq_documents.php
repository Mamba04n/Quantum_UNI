<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_rfq_documents')) {
            return;
        }

        Schema::table('supplier_rfq_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_rfq_documents', 'rfq_specifications')) {
                $table->text('rfq_specifications')->nullable()->after('rfq_response_requirements');
            }

            if (! Schema::hasColumn('supplier_rfq_documents', 'selected_item_ids')) {
                $table->json('selected_item_ids')->nullable()->after('rfq_specifications');
            }

            if (! Schema::hasColumn('supplier_rfq_documents', 'item_specifications')) {
                $table->json('item_specifications')->nullable()->after('selected_item_ids');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_rfq_documents')) {
            return;
        }

        Schema::table('supplier_rfq_documents', function (Blueprint $table) {
            foreach (['item_specifications', 'selected_item_ids', 'rfq_specifications'] as $column) {
                if (Schema::hasColumn('supplier_rfq_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
