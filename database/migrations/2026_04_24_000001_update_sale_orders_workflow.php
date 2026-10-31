<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change currency defaults to NIO
        DB::statement("ALTER TABLE sale_orders ALTER COLUMN currency SET DEFAULT 'NIO'");
        DB::statement("ALTER TABLE suppliers ALTER COLUMN currency SET DEFAULT 'NIO'");

        // Convert to varchar first to safely migrate data
        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status VARCHAR(50)");

        DB::statement("UPDATE sale_orders SET status = 'cotizacion' WHERE status = 'draft'");
        DB::statement("UPDATE sale_orders SET status = 'en_inventario_local' WHERE status = 'sent'");
        DB::statement("UPDATE sale_orders SET status = 'solicitado_proveedor' WHERE status = 'confirmed'");
        DB::statement("UPDATE sale_orders SET status = 'cancelado' WHERE status = 'cancelled'");

        // Modify the sale_orders status ENUM to fit the exact business workflow
        // New workflow: cotizacion, solicitado_proveedor, en_transito_aduanas, en_inventario_local, entregado, cancelado
        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status ENUM('cotizacion', 'solicitado_proveedor', 'en_transito_aduanas', 'en_inventario_local', 'entregado', 'cancelado') DEFAULT 'cotizacion'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE sale_orders ALTER COLUMN currency SET DEFAULT 'USD'");
        DB::statement("ALTER TABLE suppliers ALTER COLUMN currency SET DEFAULT 'USD'");

        DB::statement("ALTER TABLE sale_orders MODIFY COLUMN status ENUM('draft', 'sent', 'confirmed', 'cancelled') DEFAULT 'draft'");
    }
};
