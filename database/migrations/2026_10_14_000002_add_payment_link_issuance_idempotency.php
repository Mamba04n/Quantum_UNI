<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->uuid('issuance_key')->nullable()->unique();
            $table->char('issuance_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Reversión financiera requiere plan explícito.');
    }
};
