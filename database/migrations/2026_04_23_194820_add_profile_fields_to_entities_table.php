<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact_info');
            $table->string('phone_mobile')->nullable()->after('email');
            $table->string('phone_home')->nullable()->after('phone_mobile');
            $table->text('machinery')->nullable()->after('phone_home');
            $table->text('part_types')->nullable()->after('machinery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone_mobile', 'phone_home', 'machinery', 'part_types']);
        });
    }
};
