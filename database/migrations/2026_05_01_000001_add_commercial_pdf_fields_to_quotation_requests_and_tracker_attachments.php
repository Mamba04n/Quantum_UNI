<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_requests', 'commercial_phase')) {
                $table->string('commercial_phase')->nullable()->after('status');
            }
            if (!Schema::hasColumn('quotation_requests', 'rfq_pdf_path')) {
                $table->string('rfq_pdf_path')->nullable()->after('commercial_phase');
            }
            if (!Schema::hasColumn('quotation_requests', 'client_offer_pdf_path')) {
                $table->string('client_offer_pdf_path')->nullable()->after('rfq_pdf_path');
            }
            if (!Schema::hasColumn('quotation_requests', 'client_offer_generated_at')) {
                $table->timestamp('client_offer_generated_at')->nullable()->after('client_offer_pdf_path');
            }
            if (!Schema::hasColumn('quotation_requests', 'rfq_generated_at')) {
                $table->timestamp('rfq_generated_at')->nullable()->after('client_offer_generated_at');
            }
        });

        Schema::table('order_tracker_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('order_tracker_attachments', 'generated_by')) {
                $table->foreignId('generated_by')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_tracker_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('order_tracker_attachments', 'generated_by')) {
                $table->dropConstrainedForeignId('generated_by');
            }
        });

        Schema::table('quotation_requests', function (Blueprint $table) {
            $columns = [
                'commercial_phase',
                'rfq_pdf_path',
                'client_offer_pdf_path',
                'client_offer_generated_at',
                'rfq_generated_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('quotation_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
