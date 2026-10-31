<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('supplier_bid_selections');
        Schema::dropIfExists('supplier_bid_response_items');
        Schema::dropIfExists('supplier_bid_responses');

        Schema::create('supplier_bid_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('entities')->cascadeOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('payment_terms')->nullable();
            $table->string('general_warranty')->nullable();
            $table->string('general_delivery_time')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('received');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['quotation_request_id', 'supplier_id'], 'sbr_quote_supplier_idx');
        });

        Schema::create('supplier_bid_response_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_bid_response_id')->constrained('supplier_bid_responses')->cascadeOnDelete();
            $table->foreignId('quotation_request_item_id')->constrained('quotation_request_items')->cascadeOnDelete();
            $table->string('quoted_part_number')->nullable();
            $table->text('quoted_description')->nullable();
            $table->string('quoted_model')->nullable();
            $table->string('quoted_serial')->nullable();
            $table->string('quoted_brand')->nullable();
            $table->decimal('quoted_quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->date('estimated_arrival_date')->nullable();
            $table->string('delivery_time')->nullable();
            $table->string('warranty')->nullable();
            $table->text('internal_comment')->nullable();
            $table->string('item_status')->default('quoted');
            $table->boolean('is_alternative')->default(false);
            $table->timestamps();

            $table->unique(['supplier_bid_response_id', 'quotation_request_item_id'], 'supplier_response_item_unique');
            $table->index(['quotation_request_item_id', 'item_status'], 'sbri_quote_item_status_idx');
        });

        Schema::create('supplier_bid_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
            $table->foreignId('quotation_request_item_id')->constrained('quotation_request_items')->cascadeOnDelete();
            $table->foreignId('supplier_bid_response_item_id')->constrained('supplier_bid_response_items')->cascadeOnDelete();
            $table->foreignId('selected_supplier_id')->constrained('entities')->cascadeOnDelete();
            $table->decimal('selected_quantity', 12, 2);
            $table->decimal('selected_unit_cost', 14, 2);
            $table->string('currency', 10)->default('USD');
            $table->date('estimated_arrival_date')->nullable();
            $table->string('delivery_time')->nullable();
            $table->string('warranty')->nullable();
            $table->boolean('is_alternative')->default(false);
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['quotation_request_id', 'quotation_request_item_id'], 'supplier_selection_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bid_selections');
        Schema::dropIfExists('supplier_bid_response_items');
        Schema::dropIfExists('supplier_bid_responses');
    }
};
