<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20); // 'cxp' or 'cxc'
            $table->foreignId('account_payable_id')->nullable()->constrained('account_payables')->nullOnDelete();
            $table->foreignId('account_receivable_id')->nullable()->constrained('account_receivables')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending'); // 'pending', 'approved', 'rejected'
            $table->json('original_data');
            $table->json('requested_data');
            $table->json('changes_diff');
            $table->text('justification');
            $table->text('admin_notes')->nullable();
            $table->string('support_document_path')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_change_requests');
    }
};
