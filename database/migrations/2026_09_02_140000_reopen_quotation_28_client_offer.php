<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotation_requests')
            ->where('id', 28)
            ->where('status', 'final_pdf_generated')
            ->update([
                'status' => 'oferta_generada',
                'commercial_phase' => 'client_offer',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Reabrir una oferta es una decisión operativa y no debe revertirse automáticamente.
    }
};
