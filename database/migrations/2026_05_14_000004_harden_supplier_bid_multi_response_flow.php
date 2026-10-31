<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_bid_responses')) {
            Schema::table('supplier_bid_responses', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_bid_responses', 'supplier_bid_id')) {
                    $table->foreignId('supplier_bid_id')->nullable()->after('id')->constrained('supplier_bids')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_bid_responses', 'response_code')) {
                    $table->string('response_code')->nullable()->after('supplier_id')->index();
                }
                if (! Schema::hasColumn('supplier_bid_responses', 'supplier_document_path')) {
                    $table->string('supplier_document_path')->nullable()->after('attachment_path');
                }
            });
        }

        if (Schema::hasTable('supplier_bid_response_items')) {
            try {
                Schema::table('supplier_bid_response_items', function (Blueprint $table) {
                    $table->dropUnique('supplier_response_item_unique');
                });
            } catch (Throwable $e) {
                // The index may already be absent in restored or manually adjusted databases.
            }

            Schema::table('supplier_bid_response_items', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_bid_response_items', 'customer_request_item_id')) {
                    $table->foreignId('customer_request_item_id')->nullable()->after('quotation_request_item_id')->constrained('quotation_request_items')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_bid_response_items', 'quoted_serial_number')) {
                    $table->string('quoted_serial_number')->nullable()->after('quoted_serial');
                }
                if (! Schema::hasColumn('supplier_bid_response_items', 'payment_terms')) {
                    $table->string('payment_terms')->nullable()->after('warranty');
                }
                if (! Schema::hasColumn('supplier_bid_response_items', 'alternative_group_key')) {
                    $table->string('alternative_group_key')->nullable()->after('item_status')->index();
                }
            });

            DB::table('supplier_bid_response_items')
                ->whereNull('customer_request_item_id')
                ->update(['customer_request_item_id' => DB::raw('quotation_request_item_id')]);
        }

        if (Schema::hasTable('supplier_bid_selections')) {
            Schema::table('supplier_bid_selections', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_bid_selections', 'supplier_bid_id')) {
                    $table->foreignId('supplier_bid_id')->nullable()->after('id')->constrained('supplier_bids')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_bid_selections', 'customer_request_item_id')) {
                    $table->foreignId('customer_request_item_id')->nullable()->after('quotation_request_item_id')->constrained('quotation_request_items')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_bid_selections', 'supplier_bid_response_id')) {
                    $table->foreignId('supplier_bid_response_id')->nullable()->after('customer_request_item_id')->constrained('supplier_bid_responses')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_bid_selections', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->after('supplier_bid_response_item_id')->constrained('entities')->nullOnDelete();
                }
                if (! Schema::hasColumn('supplier_bid_selections', 'selected_brand')) {
                    $table->string('selected_brand')->nullable()->after('currency');
                }
                if (! Schema::hasColumn('supplier_bid_selections', 'selected_model')) {
                    $table->string('selected_model')->nullable()->after('selected_brand');
                }
                if (! Schema::hasColumn('supplier_bid_selections', 'selected_part_number')) {
                    $table->string('selected_part_number')->nullable()->after('selected_model');
                }
            });

            DB::table('supplier_bid_selections')
                ->whereNull('customer_request_item_id')
                ->update(['customer_request_item_id' => DB::raw('quotation_request_item_id')]);
            DB::table('supplier_bid_selections')
                ->whereNull('supplier_id')
                ->update(['supplier_id' => DB::raw('selected_supplier_id')]);
        }
    }

    public function down(): void
    {
        //
    }
};
