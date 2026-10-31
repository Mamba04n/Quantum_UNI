<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table): void {
            if (! Schema::hasColumn('account_payables', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(0)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table): void {
            if (Schema::hasColumn('account_payables', 'lock_version')) {
                $table->dropColumn('lock_version');
            }
        });
    }
};
