<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_orders', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('date')->constrained('entities')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_orders', 'client_offer_id')) {
                $table->foreignId('client_offer_id')->nullable()->after('customer_id')->constrained('client_offer_versions')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_orders', 'source_type')) {
                $table->string('source_type')->default('manual')->after('status');
            }
            if (!Schema::hasColumn('sale_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('source_type')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('sale_orders', 'generated_by')) {
                $table->foreignId('generated_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_orders', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->after('generated_by');
            }
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_order_items', 'client_offer_item_id')) {
                $table->foreignId('client_offer_item_id')->nullable()->after('sale_order_id')->constrained('client_offer_version_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_order_items', 'quotation_request_item_id')) {
                $table->foreignId('quotation_request_item_id')->nullable()->after('client_offer_item_id')->constrained('quotation_request_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_order_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('quotation_request_item_id')->constrained('products')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_order_items', 'model')) {
                $table->string('model')->nullable()->after('brand');
            }
            if (!Schema::hasColumn('sale_order_items', 'source_type')) {
                $table->string('source_type')->default('manual_pending_creation')->after('total_price');
            }
            if (!Schema::hasColumn('sale_order_items', 'inventory_status')) {
                $table->string('inventory_status')->default('pending_creation')->after('source_type');
            }
            if (!Schema::hasColumn('sale_order_items', 'purchase_status')) {
                $table->string('purchase_status')->default('required')->after('inventory_status');
            }
            if (!Schema::hasColumn('sale_order_items', 'kardex_entry_id')) {
                $table->foreignId('kardex_entry_id')->nullable()->after('purchase_status')->constrained('stock_moves')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_order_items', 'kardex_output_id')) {
                $table->foreignId('kardex_output_id')->nullable()->after('kardex_entry_id')->constrained('stock_moves')->nullOnDelete();
            }
        });

        $permissions = [
            'order.view' => 'Ver ordenes generadas',
            'order.generate' => 'Generar orden desde oferta aprobada',
            'order.cancel' => 'Cancelar orden generada',
            'order.reserve_stock' => 'Reservar stock para orden',
            'order.send_to_purchase' => 'Enviar orden a compras',
            'order.receive_items' => 'Recibir items de orden',
            'order.create_inventory_entry' => 'Crear entrada Kardex de orden',
            'order.create_inventory_output' => 'Crear salida Kardex de orden',
        ];

        if (Schema::hasTable('permissions')) {
            foreach ($permissions as $slug => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'module' => 'orders',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            foreach (['kardex_output_id', 'kardex_entry_id', 'product_id', 'quotation_request_item_id', 'client_offer_item_id'] as $column) {
                if (Schema::hasColumn('sale_order_items', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['purchase_status', 'inventory_status', 'source_type', 'model'] as $column) {
                if (Schema::hasColumn('sale_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            foreach (['generated_by', 'approved_by', 'client_offer_id', 'customer_id'] as $column) {
                if (Schema::hasColumn('sale_orders', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['generated_at', 'approved_at', 'source_type'] as $column) {
                if (Schema::hasColumn('sale_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
