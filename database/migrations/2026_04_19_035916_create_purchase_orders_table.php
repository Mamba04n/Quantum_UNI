<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('reference_number')->unique(); // Ej: PO-2026-0001
            $table->enum('status', ['draft', 'ordered', 'shipped', 'customs', 'received'])->default('draft');
            $table->decimal('total_fob', 15, 2)->default(0); // Costo internacional puro
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
