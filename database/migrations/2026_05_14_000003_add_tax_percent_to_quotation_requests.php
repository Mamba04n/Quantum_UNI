<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotation_requests') && ! Schema::hasColumn('quotation_requests', 'tax_percent')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                $table->decimal('tax_percent', 5, 2)->default(15)->after('subtotal');
            });

            DB::table('quotation_requests')->whereNull('tax_percent')->update(['tax_percent' => 15]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quotation_requests') && Schema::hasColumn('quotation_requests', 'tax_percent')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                $table->dropColumn('tax_percent');
            });
        }
    }
};
