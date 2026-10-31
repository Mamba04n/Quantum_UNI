<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotation_requests') && ! Schema::hasColumn('quotation_requests', 'offer_notes')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                $table->text('offer_notes')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quotation_requests') && Schema::hasColumn('quotation_requests', 'offer_notes')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                $table->dropColumn('offer_notes');
            });
        }
    }
};
