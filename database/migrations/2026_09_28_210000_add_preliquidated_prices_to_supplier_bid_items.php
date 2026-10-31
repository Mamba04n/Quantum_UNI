<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bid_response_items', function (Blueprint $table) {
            $table->decimal('preliquidated_unit_price', 15, 2)->nullable()->after('unit_cost');
            $table->decimal('preliquidated_unit_price_nio', 15, 2)->nullable()->after('preliquidated_unit_price');
            $table->decimal('ddp_unit_cost', 15, 2)->nullable()->after('preliquidated_unit_price_nio');
            $table->decimal('cif_unit_cost', 15, 2)->nullable()->after('ddp_unit_cost');
            $table->decimal('margin_percentage', 5, 2)->nullable()->after('cif_unit_cost');
        });

        Schema::table('supplier_bid_selections', function (Blueprint $table) {
            $table->decimal('selected_unit_price', 15, 2)->nullable()->after('selected_unit_cost');
            $table->decimal('selected_unit_price_nio', 15, 2)->nullable()->after('selected_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bid_selections', function (Blueprint $table) {
            $table->dropColumn(['selected_unit_price', 'selected_unit_price_nio']);
        });

        Schema::table('supplier_bid_response_items', function (Blueprint $table) {
            $table->dropColumn([
                'preliquidated_unit_price',
                'preliquidated_unit_price_nio',
                'ddp_unit_cost',
                'cif_unit_cost',
                'margin_percentage',
            ]);
        });
    }
};
