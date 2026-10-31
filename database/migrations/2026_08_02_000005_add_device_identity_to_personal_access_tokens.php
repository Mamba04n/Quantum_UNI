<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('device_id')->nullable()->after('name');
            $table->string('device_name', 100)->nullable()->after('device_id');
            $table->unique(['tokenable_type', 'tokenable_id', 'device_id'], 'personal_access_tokens_device_unique');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropUnique('personal_access_tokens_device_unique');
            $table->dropColumn(['device_id', 'device_name']);
        });
    }
};
