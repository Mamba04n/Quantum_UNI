<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('draft');
            $table->string('currency', 10)->default('USD');
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_time')->nullable();
            $table->string('warranty')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('proforma_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->text('winner_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(['quotation_request_id', 'status']);
            $table->index(['supplier_id', 'is_winner']);
        });

        Schema::create('supplier_bid_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_bid_id')->constrained('supplier_bids')->cascadeOnDelete();
            $table->foreignId('quotation_request_item_id')->constrained('quotation_request_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('delivery_time')->nullable();
            $table->string('warranty')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_bid_id', 'quotation_request_item_id'], 'supplier_bid_item_unique');
        });

        Schema::create('client_offer_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('reference');
            $table->string('status')->default('draft');
            $table->string('currency', 10)->default('NIO');
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('payment_terms')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quotation_request_id', 'version_number'], 'offer_version_unique');
        });

        Schema::create('client_offer_version_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_offer_version_id')->constrained('client_offer_versions')->cascadeOnDelete();
            $table->foreignId('quotation_request_item_id')->nullable()->constrained('quotation_request_items')->nullOnDelete();
            $table->string('part_number')->nullable();
            $table->string('brand')->nullable();
            $table->text('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->nullable()->constrained('quotation_requests')->cascadeOnDelete();
            $table->foreignId('quotation_request_item_id')->nullable()->constrained('quotation_request_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('status')->default('reserved');
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['quotation_request_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('client_offer_version_items');
        Schema::dropIfExists('client_offer_versions');
        Schema::dropIfExists('supplier_bid_items');
        Schema::dropIfExists('supplier_bids');
    }
};
