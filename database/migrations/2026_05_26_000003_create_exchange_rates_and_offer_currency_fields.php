<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->string('base_currency', 10)->default('USD');
                $table->string('quote_currency', 10)->default('NIO');
                $table->decimal('rate', 16, 6);
                $table->date('rate_date');
                $table->string('source')->default('manual');
                $table->boolean('is_fallback')->default(false);
                $table->timestamp('fetched_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['base_currency', 'quote_currency', 'rate_date'], 'exchange_rates_pair_date_unique');
                $table->index(['base_currency', 'quote_currency', 'rate_date'], 'exchange_rates_pair_date_idx');
            });
        }

        if (Schema::hasTable('quotation_requests')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('quotation_requests', 'currency')) {
                    $table->string('currency', 10)->default('NIO');
                }
                if (! Schema::hasColumn('quotation_requests', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 16, 6)->nullable();
                }
                if (! Schema::hasColumn('quotation_requests', 'exchange_rate_date')) {
                    $table->date('exchange_rate_date')->nullable();
                }
                if (! Schema::hasColumn('quotation_requests', 'exchange_rate_source')) {
                    $table->string('exchange_rate_source')->nullable();
                }
                if (! Schema::hasColumn('quotation_requests', 'exchange_rate_is_fallback')) {
                    $table->boolean('exchange_rate_is_fallback')->default(false);
                }
                if (! Schema::hasColumn('quotation_requests', 'exchange_rate_warning')) {
                    $table->string('exchange_rate_warning')->nullable();
                }
            });
        }

        if (Schema::hasTable('quotation_request_items')) {
            Schema::table('quotation_request_items', function (Blueprint $table) {
                if (! Schema::hasColumn('quotation_request_items', 'supplier_currency')) {
                    $table->string('supplier_currency', 10)->nullable();
                }
                if (! Schema::hasColumn('quotation_request_items', 'supplier_unit_cost_original')) {
                    $table->decimal('supplier_unit_cost_original', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('quotation_request_items', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 16, 6)->nullable();
                }
                if (! Schema::hasColumn('quotation_request_items', 'supplier_unit_cost_nio')) {
                    $table->decimal('supplier_unit_cost_nio', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('quotation_request_items', 'client_currency')) {
                    $table->string('client_currency', 10)->default('NIO');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quotation_request_items')) {
            Schema::table('quotation_request_items', function (Blueprint $table) {
                foreach (['client_currency', 'supplier_unit_cost_nio', 'exchange_rate', 'supplier_unit_cost_original', 'supplier_currency'] as $column) {
                    if (Schema::hasColumn('quotation_request_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('quotation_requests')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                foreach (['exchange_rate_warning', 'exchange_rate_is_fallback', 'exchange_rate_source', 'exchange_rate_date', 'exchange_rate', 'currency'] as $column) {
                    if (Schema::hasColumn('quotation_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('exchange_rates');
    }
};
