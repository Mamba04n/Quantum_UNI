<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_request_items', 'supplier_selection_status')) {
                $table->string('supplier_selection_status', 20)->nullable()->after('is_approved');
            }
            if (! Schema::hasColumn('quotation_request_items', 'supplier_selection_exclusion_reason')) {
                $table->string('supplier_selection_exclusion_reason', 500)->nullable()->after('supplier_selection_status');
            }
            if (! Schema::hasColumn('quotation_request_items', 'supplier_selection_excluded_by')) {
                $table->foreignId('supplier_selection_excluded_by')->nullable()->after('supplier_selection_exclusion_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quotation_request_items', 'supplier_selection_excluded_at')) {
                $table->timestamp('supplier_selection_excluded_at')->nullable()->after('supplier_selection_excluded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_request_items', 'supplier_selection_excluded_by')) {
                $table->dropConstrainedForeignId('supplier_selection_excluded_by');
            }
            $table->dropColumn([
                'supplier_selection_status',
                'supplier_selection_exclusion_reason',
                'supplier_selection_excluded_at',
            ]);
        });
    }
};
