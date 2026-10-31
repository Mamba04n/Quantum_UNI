<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar la columna enum a varchar para permitir cualquier estado
        DB::statement("ALTER TABLE sale_orders MODIFY status VARCHAR(255) DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertiremos a enum porque podría haber datos incompatibles
        // DB::statement("ALTER TABLE sale_orders MODIFY status ENUM('draft', 'sent', 'confirmed', 'cancelled') DEFAULT 'draft'");
    }
};
