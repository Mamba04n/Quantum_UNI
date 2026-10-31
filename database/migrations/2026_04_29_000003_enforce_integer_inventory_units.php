<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->update([
            'stock_on_hand' => DB::raw('ROUND(stock_on_hand)'),
            'total_cost' => DB::raw('ROUND(stock_on_hand) * cost'),
        ]);

        DB::table('stock_moves')->update([
            'quantity' => DB::raw('ROUND(quantity)'),
            'balance_after' => DB::raw('ROUND(balance_after)'),
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_on_hand')->default(0)->change();
        });

        Schema::table('stock_moves', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('balance_after')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock_on_hand', 15, 2)->default(0)->change();
        });

        Schema::table('stock_moves', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('balance_after', 15, 2)->default(0)->change();
        });
    }
};
