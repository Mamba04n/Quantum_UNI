<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_preparation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
            $table->foreignId('quotation_request_item_id')->constrained('quotation_request_items')->cascadeOnDelete();
            $table->foreignId('client_offer_item_id')->nullable()->constrained('client_offer_version_items')->nullOnDelete();
            $table->foreignId('original_supplier_quote_id')->nullable()->constrained('supplier_bid_response_items')->nullOnDelete();
            $table->foreignId('final_supplier_quote_id')->nullable()->constrained('supplier_bid_response_items')->nullOnDelete();
            $table->foreignId('original_supplier_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('final_supplier_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->decimal('original_unit_cost', 14, 2)->nullable();
            $table->decimal('final_unit_cost', 14, 2)->nullable();
            $table->decimal('quantity_to_purchase', 12, 2);
            $table->text('change_reason')->nullable();
            $table->string('status')->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['quotation_request_id', 'quotation_request_item_id'], 'purchase_prep_quote_item_unique');
            $table->index(['quotation_request_id', 'status'], 'purchase_prep_quote_status_idx');
            $table->index(['final_supplier_id', 'status'], 'purchase_prep_supplier_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_preparation_items');
    }
};
