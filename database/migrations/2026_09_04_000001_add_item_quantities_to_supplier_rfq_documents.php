<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_rfq_documents') && ! Schema::hasColumn('supplier_rfq_documents', 'rfq_item_quantities')) {
            Schema::table('supplier_rfq_documents', function (Blueprint $table): void {
                $table->json('rfq_item_quantities')->nullable()->after('item_specifications');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_rfq_documents') && Schema::hasColumn('supplier_rfq_documents', 'rfq_item_quantities')) {
            Schema::table('supplier_rfq_documents', function (Blueprint $table): void {
                $table->dropColumn('rfq_item_quantities');
            });
        }
    }
};