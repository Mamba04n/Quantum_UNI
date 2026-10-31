<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->string('supplier_contact')->nullable()->after('new_supplier_id');
            $table->string('part_name')->nullable()->after('reason');
            $table->string('part_number')->nullable()->after('part_name');
            $table->decimal('quantity', 15, 2)->nullable()->after('part_number');
            $table->decimal('supplier_unit_cost', 15, 2)->nullable()->after('quantity');
            $table->string('supplier_currency', 3)->nullable()->after('supplier_unit_cost');
            $table->decimal('client_unit_price', 15, 2)->nullable()->after('supplier_currency');
            $table->string('warranty')->nullable()->after('client_unit_price');
            $table->json('document_paths')->nullable()->after('warranty');
        });
    }

    public function down(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->dropColumn(['supplier_contact', 'part_name', 'part_number', 'quantity', 'supplier_unit_cost', 'supplier_currency', 'client_unit_price', 'warranty', 'document_paths']);
        });
    }
};
