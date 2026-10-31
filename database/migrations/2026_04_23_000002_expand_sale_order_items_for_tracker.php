<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Añadir columnas del Excel de Order Status a sale_order_items
        Schema::table('sale_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_order_items', 'replacement_part')) {
                $table->string('replacement_part')->nullable()->after('part_number'); // Columna "Reemplazo"
            }
            if (!Schema::hasColumn('sale_order_items', 'brand')) {
                $table->string('brand')->nullable()->after('description'); // Ej: Cummins, Epiroc
            }
            if (!Schema::hasColumn('sale_order_items', 'commerce')) {
                $table->string('commerce')->nullable()->after('brand'); // Ej: Woodline, CG, Ebay
            }
            if (!Schema::hasColumn('sale_order_items', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('commerce'); // Fecha compra
            }
            if (!Schema::hasColumn('sale_order_items', 'item_status')) {
                $table->string('item_status')->default('Facturado')->after('purchase_date'); // Facturado, Pendiente, etc.
            }
        });

        // Añadir columnas a sale_orders para el flujo del Excel
        Schema::table('sale_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('reference'); // N° Factura
            }
            if (!Schema::hasColumn('sale_orders', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number'); // Fecha Fact
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropColumn(['replacement_part', 'brand', 'commerce', 'purchase_date', 'item_status']);
        });
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoice_date']);
        });
    }
};
