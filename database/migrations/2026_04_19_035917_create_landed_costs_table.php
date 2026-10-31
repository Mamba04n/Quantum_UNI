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
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->decimal('freight_cost', 15, 2)->default(0); // Flete
            $table->decimal('insurance_cost', 15, 2)->default(0); // Seguro
            $table->decimal('customs_duties', 15, 2)->default(0); // Arancel DAI (Aduana)
            $table->decimal('import_tax', 15, 2)->default(0); // IVA de importación
            $table->decimal('agency_fees', 15, 2)->default(0); // Agencia y almacenaje
            $table->decimal('total_landed_cost', 15, 2)->default(0); // Suma total de los gastos aduanales
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landed_costs');
    }
};
