<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_documents')) {
            Schema::create('purchase_order_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_request_id')->unique()->constrained('approval_requests')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('sale_order_id')->nullable()->constrained('sale_orders')->nullOnDelete();
                $table->foreignId('order_status_id')->nullable()->constrained('order_statuses')->nullOnDelete();
                $table->string('status')->default('draft')->index();
                $table->string('quote_to_name');
                $table->string('consignee_name');
                $table->text('consignee_comment')->nullable();
                $table->text('consignee_urgent_comment')->nullable();
                $table->string('consignee_address_1');
                $table->string('consignee_address_2')->nullable();
                $table->string('consignee_country');
                $table->string('consignee_city');
                $table->string('consignee_phone');
                $table->text('consignee_emails')->nullable();
                $table->string('shipping_method')->nullable();
                $table->string('shipping_terms')->nullable();
                $table->string('incoterm')->nullable();
                $table->string('carrier_name')->nullable();
                $table->date('estimated_shipping_date')->nullable();
                $table->date('estimated_arrival_date')->nullable();
                $table->text('shipping_comments')->nullable();
                $table->text('supplier_comments')->nullable();
                $table->text('internal_comments')->nullable();
                $table->text('document_footer_comments')->nullable();
                $table->text('special_instructions')->nullable();
                $table->string('pdf_path')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_documents');
    }
};
