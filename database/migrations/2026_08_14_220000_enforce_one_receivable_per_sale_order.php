<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_receivables', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0)->after('status');
            $table->unique('sales_order_id', 'account_receivables_sales_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_receivables', function (Blueprint $table): void {
            $table->dropUnique('account_receivables_sales_order_unique');
            $table->dropColumn('lock_version');
        });
    }
};
