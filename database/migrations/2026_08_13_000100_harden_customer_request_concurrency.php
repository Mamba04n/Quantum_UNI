<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quotation_requests', 'submission_token')) {
            Schema::table('quotation_requests', function (Blueprint $table): void {
                $table->uuid('submission_token')->nullable()->after('reference');
                $table->unique('submission_token', 'quotation_requests_submission_token_unique');
            });
        }

        $next = DB::table('quotation_requests')
            ->pluck('reference')
            ->map(fn ($reference) => preg_match('/^SC-(\d+)$/', (string) $reference, $matches) ? (int) $matches[1] : 0)
            ->max() + 1;

        DB::table('document_sequences')->insertOrIgnore([
            'name' => 'customer_request',
            'next_value' => max(1, $next),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('document_sequences')->where('name', 'customer_request')->delete();

        if (Schema::hasColumn('quotation_requests', 'submission_token')) {
            Schema::table('quotation_requests', function (Blueprint $table): void {
                $table->dropUnique('quotation_requests_submission_token_unique');
                $table->dropColumn('submission_token');
            });
        }
    }
};
