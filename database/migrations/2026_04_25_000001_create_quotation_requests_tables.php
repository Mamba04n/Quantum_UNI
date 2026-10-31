<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date');

            $table->string('quote_to_name');
            $table->string('quote_to_email')->nullable();
            $table->string('quote_to_email_alt')->nullable();

            $table->string('consignee_name')->nullable();
            $table->text('consignee_handling_notes')->nullable();
            $table->text('consignee_address')->nullable();

            $table->date('delivery_date')->nullable();
            $table->string('ship_via')->nullable();
            $table->string('shipping_conditions')->nullable();
            $table->string('payment_terms')->nullable();

            $table->string('requested_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('department')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->string('status', 32)->default('borrador');
            $table->text('notes')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('quotation_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
            $table->string('part_number')->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('internal_request_ref')->nullable();
            $table->string('brand')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_request_items');
        Schema::dropIfExists('quotation_requests');
    }
};
