<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_documents')) {
            return;
        }

        Schema::table('purchase_order_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_documents', 'sent_to_provider_at')) {
                $table->timestamp('sent_to_provider_at')->nullable()->after('generated_at');
            }

            if (! Schema::hasColumn('purchase_order_documents', 'sent_to_provider_by')) {
                $table->foreignId('sent_to_provider_by')->nullable()->after('sent_to_provider_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_order_documents')) {
            return;
        }

        Schema::table('purchase_order_documents', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_documents', 'sent_to_provider_by')) {
                $table->dropConstrainedForeignId('sent_to_provider_by');
            }

            if (Schema::hasColumn('purchase_order_documents', 'sent_to_provider_at')) {
                $table->dropColumn('sent_to_provider_at');
            }
        });
    }
};
