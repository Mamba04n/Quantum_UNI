<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_requests')) {
            Schema::table('approval_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('approval_requests', 'active_key')) {
                    $table->string('active_key')->nullable()->after('source_id')->unique();
                }
            });
        }

        if (Schema::hasTable('sale_orders')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_orders', 'approval_request_id')) {
                    $table->foreignId('approval_request_id')
                        ->nullable()
                        ->after('client_offer_id')
                        ->constrained('approval_requests')
                        ->nullOnDelete()
                        ->unique();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_orders') && Schema::hasColumn('sale_orders', 'approval_request_id')) {
            Schema::table('sale_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('approval_request_id');
            });
        }

        if (Schema::hasTable('approval_requests') && Schema::hasColumn('approval_requests', 'active_key')) {
            Schema::table('approval_requests', function (Blueprint $table) {
                $table->dropUnique(['active_key']);
                $table->dropColumn('active_key');
            });
        }
    }
};
