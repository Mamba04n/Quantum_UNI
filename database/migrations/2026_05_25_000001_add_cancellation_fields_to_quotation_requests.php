<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotation_requests')) {
            return;
        }

        Schema::table('quotation_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_requests', 'cancellation_category')) {
                $table->string('cancellation_category', 80)->nullable()->after('status');
            }

            if (! Schema::hasColumn('quotation_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancellation_category');
            }

            if (! Schema::hasColumn('quotation_requests', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotation_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quotation_requests')) {
            return;
        }

        Schema::table('quotation_requests', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_requests', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }

            foreach (['cancelled_at', 'cancellation_reason', 'cancellation_category'] as $column) {
                if (Schema::hasColumn('quotation_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
