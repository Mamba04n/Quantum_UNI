<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_link_items', fn (Blueprint $table) => $table->string('image_path', 2048)->nullable());
    }

    public function down(): void
    {
        throw new RuntimeException('Las imágenes emitidas requieren reversión explícita.');
    }
};
