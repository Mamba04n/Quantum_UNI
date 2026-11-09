<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_client_id')->constrained('integration_clients')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('external_reference', 100);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_code', 100);
            $table->text('description');
            $table->decimal('quantity', 12, 2);
            $table->string('unit_of_measure', 20)->default('UND');
            $table->date('required_date')->nullable();
            $table->string('priority', 20)->default('MEDIUM');
            $table->text('justification')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->string('status', 30)->default('REQUESTED');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['integration_client_id', 'external_reference'], 'idx_ipr_client_ext_ref');
            $table->index('status', 'idx_ipr_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_purchase_requests');
    }
};
