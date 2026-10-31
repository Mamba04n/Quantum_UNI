<?php

use App\Models\SupplierRfqDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_rfq_documents')) {
            return;
        }

        Schema::table('supplier_rfq_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_rfq_documents', 'rfq_number')) {
                $table->string('rfq_number')->nullable()->after('supplier_id');
            }

            if (! Schema::hasColumn('supplier_rfq_documents', 'rfq_response_requirements')) {
                $table->text('rfq_response_requirements')->nullable()->after('consignee_emails');
            }
        });

        SupplierRfqDocument::query()
            ->where('status', 'pdf_generated')
            ->update(['status' => 'generated']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_rfq_documents')) {
            return;
        }

        Schema::table('supplier_rfq_documents', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_rfq_documents', 'rfq_response_requirements')) {
                $table->dropColumn('rfq_response_requirements');
            }

            if (Schema::hasColumn('supplier_rfq_documents', 'rfq_number')) {
                $table->dropColumn('rfq_number');
            }
        });
    }
};
