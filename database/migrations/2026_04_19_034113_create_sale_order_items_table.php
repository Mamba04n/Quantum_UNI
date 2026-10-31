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
        Schema::create('sale_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete();
            
            // Datos del Renglón/Item
            $table->string('part_number')->nullable(); // Ej: 60113115
            $table->string('description'); // Ej: Perno
            
            // Cantidades y Totales
            $table->decimal('quantity', 10, 2); // Para aguantar medios litros, etc.
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_order_items');
    }
};
