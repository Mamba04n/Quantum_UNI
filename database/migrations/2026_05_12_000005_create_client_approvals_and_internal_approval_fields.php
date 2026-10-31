<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_approvals')) {
            Schema::create('client_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
                $table->foreignId('client_offer_id')->nullable()->constrained('client_offer_versions')->nullOnDelete();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('status');
                $table->string('approved_type');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['quotation_request_id', 'status']);
                $table->index(['client_offer_id', 'status']);
            });
        }

        if (Schema::hasTable('approval_requests')) {
            Schema::table('approval_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('approval_requests', 'approval_code')) {
                    $table->string('approval_code')->nullable()->after('id')->unique();
                }
                if (! Schema::hasColumn('approval_requests', 'approval_type')) {
                    $table->string('approval_type')->nullable()->after('request_type')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_requests')) {
            Schema::table('approval_requests', function (Blueprint $table) {
                if (Schema::hasColumn('approval_requests', 'approval_code')) {
                    $table->dropUnique(['approval_code']);
                    $table->dropColumn('approval_code');
                }
                if (Schema::hasColumn('approval_requests', 'approval_type')) {
                    $table->dropColumn('approval_type');
                }
            });
        }

        Schema::dropIfExists('client_approvals');
    }
};
