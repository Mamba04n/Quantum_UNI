<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_stage_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->string('from_stage');
            $table->string('to_stage');
            $table->string('status')->default('pending');
            $table->text('reason');
            $table->text('warning')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['quotation_request_id', 'status'], 'quote_stage_requests_quote_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_stage_change_requests');
    }
};
