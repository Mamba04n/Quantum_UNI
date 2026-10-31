<?php

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
            if (! Schema::hasColumn('supplier_rfq_documents', 'consignee_contact_name')) {
                $table->string('consignee_contact_name')->nullable()->after('consignee_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_rfq_documents')) {
            return;
        }

        Schema::table('supplier_rfq_documents', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_rfq_documents', 'consignee_contact_name')) {
                $table->dropColumn('consignee_contact_name');
            }
        });
    }
};
