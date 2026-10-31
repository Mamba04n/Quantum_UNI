<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATETIME prevents MariaDB's implicit first-TIMESTAMP ON UPDATE behavior.
        Schema::table('payment_links', fn (Blueprint $table) => $table->dateTime('expires_at')->change());
    }

    public function down(): void
    {
        throw new RuntimeException('No reintroducir expiración con actualización implícita.');
    }
};
