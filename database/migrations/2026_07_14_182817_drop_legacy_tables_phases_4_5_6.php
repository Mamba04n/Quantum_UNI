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
        Schema::disableForeignKeyConstraints();

        // Phase 4: Suppliers legacy
        Schema::dropIfExists('supplier_bank_accounts');
        Schema::dropIfExists('supplier_product_types');
        Schema::dropIfExists('supplier_payment_methods');
        Schema::dropIfExists('supplier_machine_types');
        Schema::dropIfExists('supplier_brands');
        Schema::dropIfExists('suppliers');

        // Phase 5: Products legacy
        Schema::dropIfExists('productos_imagenes');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('marcas');

        // Phase 6: Machines legacy
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('machine_parts');
        Schema::dropIfExists('machines');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One way migration, no easy down unless we recreate all tables.
    }
};
