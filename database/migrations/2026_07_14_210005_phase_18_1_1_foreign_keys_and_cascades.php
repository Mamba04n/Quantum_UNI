<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. sale_orders: client_offer_id -> client_offer_version_id
        if (Schema::hasTable('sale_orders')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                if (Schema::hasColumn('sale_orders', 'client_offer_id')) {
                    $table->dropForeign(['client_offer_id']);
                    $table->dropColumn('client_offer_id');
                }
                if (!Schema::hasColumn('sale_orders', 'client_offer_version_id')) {
                    $table->foreignId('client_offer_version_id')->nullable()->after('customer_id')->constrained('client_offer_versions')->nullOnDelete();
                }
            });
        }

        // 2. Corregir CASCADEs peligrosos a RESTRICT
        $this->updateForeignKey('activity_logs', 'user_id', 'users', 'restrict');
        $this->updateForeignKey('account_payables', 'entity_id', 'entities', 'restrict');
        $this->updateForeignKey('account_receivables', 'entity_id', 'entities', 'restrict');
        $this->updateForeignKey('client_machines', 'entity_id', 'entities', 'restrict');
        $this->updateForeignKey('purchase_orders', 'supplier_id', 'entities', 'restrict');
        $this->updateForeignKey('stock_moves', 'product_id', 'products', 'restrict');
        $this->updateForeignKey('tickets', 'customer_id', 'users', 'restrict');
        $this->updateForeignKey('ticket_messages', 'sender_id', 'users', 'restrict');
        $this->updateForeignKey('ticket_notes', 'admin_id', 'users', 'restrict');
        $this->updateForeignKey('supplier_bid_responses', 'supplier_id', 'entities', 'restrict');
        $this->updateForeignKey('supplier_bid_selections', 'selected_supplier_id', 'entities', 'restrict');
        
        // Convert DOUBLE/FLOAT to DECIMAL
        $this->convertMonetaryColumns();
    }

    private function updateForeignKey(string $table, string $column, string $references, string $onDelete)
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            $fkName = "{$table}_{$column}_foreign";
            
            try {
                // MySQL / MariaDB specific
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            } catch (\Exception $e) {
                // Ignore if not exists
            }

            try {
                // Si la FK ya existe con otro nombre o ya se agregó (en caso de re-run parcial), esto también podría fallar
                Schema::table($table, function (Blueprint $t) use ($column, $references, $onDelete) {
                    if ($onDelete === 'restrict') {
                        $t->foreign($column)->references('id')->on($references)->restrictOnDelete();
                    } else {
                        $t->foreign($column)->references('id')->on($references)->nullOnDelete();
                    }
                });
            } catch (\Exception $e) {
                // Already added? Ignore
            }
        }
    }

    private function convertMonetaryColumns()
    {
        $decimal18_2 = [
            'account_payables' => ['amount', 'balance'],
            'account_receivables' => ['amount', 'balance'],
            'purchase_orders' => ['subtotal', 'tax_amount', 'total_amount'],
            'purchase_order_items' => ['unit_price', 'total_price'],
            'sale_orders' => ['subtotal', 'tax_amount', 'total_amount'],
            'sale_order_items' => ['unit_price', 'line_total'],
            'client_offer_versions' => ['subtotal', 'tax_amount', 'total_amount'],
            'client_offer_version_items' => ['unit_price', 'total_price'],
            'journal_lines' => ['debit', 'credit'],
            'supplier_bid_items' => ['target_price'],
            'supplier_bid_response_items' => ['unit_price', 'total_price']
        ];

        $decimal18_4 = [
            'purchase_order_items' => ['quantity'],
            'sale_order_items' => ['quantity'],
            'client_offer_version_items' => ['quantity'],
            'inventory_reservations' => ['quantity'],
            'stock_moves' => ['quantity'],
            'supplier_bid_items' => ['quantity'],
            'supplier_bid_response_items' => ['quantity', 'lead_time_days'] 
        ];

        foreach ($decimal18_2 as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        try {
                            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(18,2) NOT NULL DEFAULT 0.00");
                        } catch (\Exception $e) { }
                    }
                }
            }
        }

        foreach ($decimal18_4 as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        try {
                            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(18,4) NOT NULL DEFAULT 0.0000");
                        } catch (\Exception $e) { }
                    }
                }
            }
        }
    }

    public function down(): void
    {
    }
};
