<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->boolean('is_active')->default(true);
            $table->boolean('automatic_updates_enabled')->default(false);
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            ['code' => 'NIO', 'name' => 'Córdoba nicaragüense', 'symbol' => 'C$', 'is_active' => true, 'automatic_updates_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'symbol' => '$', 'is_active' => true, 'automatic_updates_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true, 'automatic_updates_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'INR', 'name' => 'Rupia india', 'symbol' => '₹', 'is_active' => true, 'automatic_updates_enabled' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
