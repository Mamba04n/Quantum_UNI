<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotation_requests')
            ->where('id', 28)
            ->whereIn('status', ['respuesta_recibida', 'proveedor_seleccionado'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('supplier_bid_selections')
                    ->whereColumn('supplier_bid_selections.quotation_request_id', 'quotation_requests.id');
            })
            ->update([
                'status' => 'oferta_generada',
                'commercial_phase' => 'client_offer',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // La etapa previa exacta no puede reconstruirse de forma segura.
    }
};
