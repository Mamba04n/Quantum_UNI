<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotation_requests')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('quotation_requests', 'company_issuer')) {
                    $table->string('company_issuer', 50)->default('quantum')->after('currency');
                }
            });
        }

        if (Schema::hasTable('client_offer_versions')) {
            Schema::table('client_offer_versions', function (Blueprint $table) {
                if (! Schema::hasColumn('client_offer_versions', 'company_issuer')) {
                    $table->string('company_issuer', 50)->default('quantum')->after('currency');
                }
            });
        }

        if (Schema::hasTable('sale_orders')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_orders', 'company_issuer')) {
                    $table->string('company_issuer', 50)->default('quantum')->after('currency');
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'company_issuer')) {
                    $table->string('company_issuer', 50)->default('quantum')->after('invoice_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quotation_requests') && Schema::hasColumn('quotation_requests', 'company_issuer')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                $table->dropColumn('company_issuer');
            });
        }

        if (Schema::hasTable('client_offer_versions') && Schema::hasColumn('client_offer_versions', 'company_issuer')) {
            Schema::table('client_offer_versions', function (Blueprint $table) {
                $table->dropColumn('company_issuer');
            });
        }

        if (Schema::hasTable('sale_orders') && Schema::hasColumn('sale_orders', 'company_issuer')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                $table->dropColumn('company_issuer');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'company_issuer')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('company_issuer');
            });
        }
    }
};
