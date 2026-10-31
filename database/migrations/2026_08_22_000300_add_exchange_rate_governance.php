<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->boolean('is_fixed')->default(false)->after('automatic_updates_enabled');
            $table->unsignedSmallInteger('automatic_update_interval_hours')->default(24)->after('is_fixed');
        });

        DB::table('currencies')->where('code', 'USD')->update([
            'is_fixed' => true,
            'automatic_updates_enabled' => false,
        ]);

        Schema::create('exchange_rate_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('base_currency', 10);
            $table->string('quote_currency', 10)->default('NIO');
            $table->decimal('proposed_rate', 16, 6);
            $table->date('rate_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_change_requests');
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropColumn(['is_fixed', 'automatic_update_interval_hours']);
        });
    }
};
