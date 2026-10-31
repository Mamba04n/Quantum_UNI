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
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // Numero de orden: OC0002
            $table->date('date');
            
            // Datos del Cliente (Facturar a / Detalles de envío)
            $table->string('client_name');
            $table->string('client_ruc')->nullable();
            $table->text('shipping_address')->nullable();
            
            // Proveedor
            $table->string('provider_name')->nullable();
            $table->string('provider_ruc')->nullable();
            
            // Condiciones
            $table->enum('payment_type', ['Contado', 'Crédito'])->default('Contado');
            $table->enum('status', ['draft', 'sent', 'confirmed', 'cancelled'])->default('draft');
            
            // Cálculos Monetarios
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(15.00); // 15% como default
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            
            // Extras
            $table->string('currency', 3)->default('USD');
            $table->text('terms_and_conditions')->nullable(); // Ej: Envio Aereo Via Fedex
            $table->text('notes')->nullable();
            
            // No podemos depender de 'clients' todavía si no has hecho ese módulo, 
            // así que guardaremos nombres directamente por flexibilidad.
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
    }
};
