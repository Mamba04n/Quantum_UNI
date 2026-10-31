<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_receipts')) {
            Schema::create('purchase_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->string('reference')->unique();
                $table->date('received_date');
                $table->string('status', 40)->default('received');
                $table->string('document_path')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['purchase_order_id', 'status']);
            });
        }

        if (! Schema::hasTable('purchase_receipt_items')) {
            Schema::create('purchase_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->decimal('quantity_received', 15, 2);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->foreignId('kardex_entry_id')->nullable()->constrained('stock_moves')->nullOnDelete();
                $table->string('status', 40)->default('received');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['purchase_order_item_id', 'status'], 'receipt_item_po_item_status_idx');
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'received_by')) {
                $table->foreignId('received_by')->nullable()->after('generated_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_orders', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('received_by');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'received_quantity')) {
                $table->decimal('received_quantity', 15, 2)->default(0)->after('quantity');
            }

            if (! Schema::hasColumn('purchase_order_items', 'received_by')) {
                $table->foreignId('received_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_order_items', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('received_by');
            }

            if (! Schema::hasColumn('purchase_order_items', 'kardex_entry_id')) {
                $table->foreignId('kardex_entry_id')->nullable()->after('received_at')->constrained('stock_moves')->nullOnDelete();
            }

            if (! Schema::hasColumn('purchase_order_items', 'receipt_document_path')) {
                $table->string('receipt_document_path')->nullable()->after('kardex_entry_id');
            }
        });

        Schema::table('stock_moves', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_moves', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')->nullable()->after('product_id')->constrained('purchase_orders')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_moves', 'purchase_order_item_id')) {
                $table->foreignId('purchase_order_item_id')->nullable()->after('purchase_order_id')->constrained('purchase_order_items')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_moves', 'purchase_receipt_id')) {
                $table->foreignId('purchase_receipt_id')->nullable()->after('purchase_order_item_id')->constrained('purchase_receipts')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_moves', 'purchase_receipt_item_id')) {
                $table->foreignId('purchase_receipt_item_id')->nullable()->after('purchase_receipt_id')->constrained('purchase_receipt_items')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_moves', 'sale_order_id')) {
                $table->foreignId('sale_order_id')->nullable()->after('purchase_receipt_item_id')->constrained('sale_orders')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_moves', 'sale_order_item_id')) {
                $table->foreignId('sale_order_item_id')->nullable()->after('sale_order_id')->constrained('sale_order_items')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_moves', 'source_module')) {
                $table->string('source_module', 80)->nullable()->after('state');
            }

            if (! Schema::hasColumn('stock_moves', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_module');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_moves', function (Blueprint $table) {
            foreach (['purchase_order_id', 'purchase_order_item_id', 'purchase_receipt_id', 'purchase_receipt_item_id', 'sale_order_id', 'sale_order_item_id'] as $column) {
                if (Schema::hasColumn('stock_moves', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['source_module', 'source_id'] as $column) {
                if (Schema::hasColumn('stock_moves', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach (['received_by', 'kardex_entry_id'] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['received_quantity', 'received_at', 'receipt_document_path'] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'received_by')) {
                $table->dropConstrainedForeignId('received_by');
            }

            if (Schema::hasColumn('purchase_orders', 'received_at')) {
                $table->dropColumn('received_at');
            }
        });

        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
