<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->foreignId('integration_client_id')->nullable()->after('reserved_by')->constrained('integration_clients')->nullOnDelete();
            $table->string('source_type', 30)->default('ERP_INTERNAL')->after('integration_client_id');
            $table->string('external_reference', 100)->nullable()->after('source_type');
            $table->string('warehouse_code', 50)->default('BODEGA-CENTRAL')->after('external_reference');
            $table->decimal('consumed_quantity', 12, 2)->default(0.00)->after('quantity');
            $table->timestamp('consumed_at')->nullable()->after('released_at');
            $table->foreignId('stock_move_id')->nullable()->after('consumed_at')->constrained('stock_moves')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->after('stock_move_id');
            $table->json('external_context')->nullable()->after('idempotency_key');
            $table->unsignedInteger('version')->default(1)->after('external_context');

            $table->index(['integration_client_id', 'external_reference'], 'idx_inv_res_client_ext_ref');
            $table->index(['source_type', 'status'], 'idx_inv_res_source_status');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->dropIndex('idx_inv_res_client_ext_ref');
            $table->dropIndex('idx_inv_res_source_status');
            $table->dropForeign(['integration_client_id']);
            $table->dropForeign(['stock_move_id']);
            $table->dropColumn([
                'integration_client_id',
                'source_type',
                'external_reference',
                'warehouse_code',
                'consumed_quantity',
                'consumed_at',
                'stock_move_id',
                'idempotency_key',
                'external_context',
                'version',
            ]);
        });
    }
};
