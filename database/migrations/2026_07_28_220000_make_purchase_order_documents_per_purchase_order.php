<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL usa el índice único original para soportar la llave foránea.
        // Primero se crea un índice normal, para poder sustituirlo sin romperla.
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->index('approval_request_id', 'pod_approval_lookup_index');
        });

        Schema::table('purchase_order_documents', function (Blueprint $table) {
            // El diseño original permitía un solo documento por aprobación.
            // Una aprobación puede generar varias OC (una por proveedor).
            $table->dropUnique(['approval_request_id']);
            $table->unique(['approval_request_id', 'purchase_order_id'], 'pod_approval_purchase_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->dropUnique('pod_approval_purchase_unique');
            $table->unique('approval_request_id');
            $table->dropIndex('pod_approval_lookup_index');
        });
    }
};
