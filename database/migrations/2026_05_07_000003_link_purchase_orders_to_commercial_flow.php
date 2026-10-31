<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            try {
                DB::statement("ALTER TABLE purchase_orders MODIFY status VARCHAR(40) DEFAULT 'draft'");
            } catch (\Throwable $e) {
                //
            }

            Schema::table('purchase_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_orders', 'quotation_request_id')) {
                    $table->foreignId('quotation_request_id')->nullable()->after('supplier_id')->constrained('quotation_requests')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_orders', 'sale_order_id')) {
                    $table->foreignId('sale_order_id')->nullable()->after('quotation_request_id')->constrained('sale_orders')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_orders', 'supplier_bid_response_id')) {
                    $table->foreignId('supplier_bid_response_id')->nullable()->after('sale_order_id')->constrained('supplier_bid_responses')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_orders', 'source_type')) {
                    $table->string('source_type', 40)->default('manual')->after('status');
                }

                if (! Schema::hasColumn('purchase_orders', 'currency')) {
                    $table->string('currency', 10)->default('USD')->after('total_fob');
                }

                if (! Schema::hasColumn('purchase_orders', 'generated_by')) {
                    $table->foreignId('generated_by')->nullable()->after('expected_date')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_orders', 'generated_at')) {
                    $table->timestamp('generated_at')->nullable()->after('generated_by');
                }

                if (! Schema::hasColumn('purchase_orders', 'notes')) {
                    $table->text('notes')->nullable()->after('generated_at');
                }
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            try {
                DB::statement('ALTER TABLE purchase_order_items MODIFY product_id BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                //
            }

            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_order_items', 'quotation_request_item_id')) {
                    $table->foreignId('quotation_request_item_id')->nullable()->after('product_id')->constrained('quotation_request_items')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_order_items', 'sale_order_item_id')) {
                    $table->foreignId('sale_order_item_id')->nullable()->after('quotation_request_item_id')->constrained('sale_order_items')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_order_items', 'supplier_bid_selection_id')) {
                    $table->foreignId('supplier_bid_selection_id')->nullable()->after('sale_order_item_id')->constrained('supplier_bid_selections')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_order_items', 'supplier_bid_response_item_id')) {
                    $table->foreignId('supplier_bid_response_item_id')->nullable()->after('supplier_bid_selection_id')->constrained('supplier_bid_response_items')->nullOnDelete();
                }

                if (! Schema::hasColumn('purchase_order_items', 'part_name')) {
                    $table->string('part_name')->nullable()->after('supplier_bid_response_item_id');
                }

                if (! Schema::hasColumn('purchase_order_items', 'part_number')) {
                    $table->string('part_number')->nullable()->after('part_name');
                }

                if (! Schema::hasColumn('purchase_order_items', 'brand')) {
                    $table->string('brand')->nullable()->after('part_number');
                }

                if (! Schema::hasColumn('purchase_order_items', 'model')) {
                    $table->string('model')->nullable()->after('brand');
                }

                if (! Schema::hasColumn('purchase_order_items', 'currency')) {
                    $table->string('currency', 10)->default('USD')->after('total_price');
                }

                if (! Schema::hasColumn('purchase_order_items', 'expected_date')) {
                    $table->date('expected_date')->nullable()->after('currency');
                }

                if (! Schema::hasColumn('purchase_order_items', 'status')) {
                    $table->string('status', 40)->default('ordered')->after('expected_date');
                }

                if (! Schema::hasColumn('purchase_order_items', 'notes')) {
                    $table->text('notes')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach ([
                'quotation_request_item_id',
                'sale_order_item_id',
                'supplier_bid_selection_id',
                'supplier_bid_response_item_id',
                'part_name',
                'part_number',
                'brand',
                'model',
                'currency',
                'expected_date',
                'status',
                'notes',
            ] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) {
                    if (str_ends_with($column, '_id')) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'quotation_request_id',
                'sale_order_id',
                'supplier_bid_response_id',
                'source_type',
                'currency',
                'generated_by',
                'generated_at',
                'notes',
            ] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    if (str_ends_with($column, '_id') || $column === 'generated_by') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
