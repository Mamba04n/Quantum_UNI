<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'quality_types')) {
                $table->json('quality_types')->nullable()->after('status');
            }
            if (! Schema::hasColumn('entities', 'supplier_metadata')) {
                $table->json('supplier_metadata')->nullable()->after('quality_types');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'quality_types')) {
                $table->dropColumn('quality_types');
            }
            if (Schema::hasColumn('entities', 'supplier_metadata')) {
                $table->dropColumn('supplier_metadata');
            }
        });
    }
};
