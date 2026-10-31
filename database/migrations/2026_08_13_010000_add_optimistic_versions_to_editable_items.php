<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('version')->default(1)->after('status');
        });

    }

    public function down(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
