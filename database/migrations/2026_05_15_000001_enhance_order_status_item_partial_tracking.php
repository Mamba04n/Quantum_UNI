<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_status_items')) {
            Schema::table('order_status_items', function (Blueprint $table) {
                $this->decimalIfMissing($table, 'quantity_shipped', 'quantity_confirmed');
                $this->decimalIfMissing($table, 'quantity_in_customs', 'quantity_shipped');
                $this->decimalIfMissing($table, 'quantity_dispatched', 'quantity_kardex_output');
                $this->decimalIfMissing($table, 'quantity_cancelled', 'quantity_delivered');
                $this->decimalIfMissing($table, 'quantity_pending', 'quantity_cancelled');

                $this->dateIfMissing($table, 'estimated_supplier_dispatch_date', 'quantity_pending');
                $this->dateIfMissing($table, 'actual_supplier_dispatch_date', 'estimated_supplier_dispatch_date');
                $this->dateIfMissing($table, 'customs_entry_date', 'actual_arrival_date');
                $this->dateIfMissing($table, 'customs_release_date', 'customs_entry_date');
                $this->dateIfMissing($table, 'warehouse_received_date', 'customs_release_date');
                $this->dateIfMissing($table, 'kardex_entry_date', 'warehouse_received_date');
                $this->dateIfMissing($table, 'kardex_output_date', 'kardex_entry_date');
                $this->dateIfMissing($table, 'delivery_dispatch_date', 'kardex_output_date');
                $this->dateTimeIfMissing($table, 'delivered_at', 'delivery_dispatch_date');
            });
        }

        if (! Schema::hasTable('shipment_batches')) {
            Schema::create('shipment_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_status_id')->constrained('order_statuses')->cascadeOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->string('shipment_code')->index();
                $table->string('carrier_name')->nullable();
                $table->string('tracking_number')->nullable()->index();
                $table->string('origin_country')->nullable();
                $table->string('destination_country')->nullable();
                $table->date('departure_date')->nullable();
                $table->date('estimated_arrival_date')->nullable();
                $table->date('actual_arrival_date')->nullable();
                $table->string('status')->default('preparando')->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['order_status_id', 'shipment_code'], 'shipment_batches_order_code_unique');
            });
        }

        if (! Schema::hasTable('shipment_batch_items')) {
            Schema::create('shipment_batch_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipment_batch_id')->constrained('shipment_batches')->cascadeOnDelete();
                $table->foreignId('order_status_item_id')->constrained('order_status_items')->cascadeOnDelete();
                $table->decimal('quantity_shipped', 12, 2);
                $table->timestamps();

                $table->unique(['shipment_batch_id', 'order_status_item_id'], 'shipment_batch_item_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_batch_items');
        Schema::dropIfExists('shipment_batches');

        if (Schema::hasTable('order_status_items')) {
            Schema::table('order_status_items', function (Blueprint $table) {
                $columns = [
                    'quantity_shipped',
                    'quantity_in_customs',
                    'quantity_dispatched',
                    'quantity_cancelled',
                    'quantity_pending',
                    'estimated_supplier_dispatch_date',
                    'actual_supplier_dispatch_date',
                    'customs_entry_date',
                    'customs_release_date',
                    'warehouse_received_date',
                    'kardex_entry_date',
                    'kardex_output_date',
                    'delivery_dispatch_date',
                    'delivered_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('order_status_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function decimalIfMissing(Blueprint $table, string $column, string $after): void
    {
        if (! Schema::hasColumn('order_status_items', $column)) {
            $table->decimal($column, 12, 2)->default(0)->after($after);
        }
    }

    private function dateIfMissing(Blueprint $table, string $column, string $after): void
    {
        if (! Schema::hasColumn('order_status_items', $column)) {
            $table->date($column)->nullable()->after($after);
        }
    }

    private function dateTimeIfMissing(Blueprint $table, string $column, string $after): void
    {
        if (! Schema::hasColumn('order_status_items', $column)) {
            $table->dateTime($column)->nullable()->after($after);
        }
    }
};
