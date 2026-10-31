<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracker_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('order_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('current_phase')->default('solicitud');
            $table->string('current_status')->default('abierto');
            $table->string('main_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('order_tracker_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('order_trackers')->cascadeOnDelete();
            $table->string('reference_type');
            $table->string('reference_code')->nullable();
            $table->string('source_module');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->unique(['source_module', 'source_id'], 'tracker_ref_source_unique');
            $table->index(['reference_type', 'reference_code']);
        });

        Schema::create('order_tracker_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('order_trackers')->cascadeOnDelete();
            $table->string('action');
            $table->text('description');
            $table->string('phase')->default('general');
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tracker_id', 'phase']);
            $table->index(['source_module', 'source_id']);
        });

        Schema::create('order_tracker_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('order_trackers')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('order_tracker_activities')->nullOnDelete();
            $table->string('phase')->default('general');
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['tracker_id', 'phase']);
        });

        Schema::create('order_tracker_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('order_trackers')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('order_tracker_activities')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->string('visibility')->default('internal');
            $table->timestamps();

            $table->index(['tracker_id', 'visibility']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('order_trackers')->cascadeOnDelete();
            $table->string('request_type');
            $table->string('source_module');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'request_type']);
            $table->index(['source_module', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('order_tracker_comments');
        Schema::dropIfExists('order_tracker_attachments');
        Schema::dropIfExists('order_tracker_activities');
        Schema::dropIfExists('order_tracker_references');
        Schema::dropIfExists('order_trackers');
        Schema::dropIfExists('order_tracker_sequences');
    }
};
