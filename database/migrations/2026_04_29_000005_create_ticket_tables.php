<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year')->unique();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('subject');
            $table->longText('description');
            $table->string('priority', 20)->default('media');
            $table->string('status', 32)->default('abierto');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('related_sku')->nullable();
            $table->foreignId('related_stock_move_id')->nullable()->constrained('stock_moves')->nullOnDelete();
            $table->foreignId('quotation_request_id')->nullable()->constrained('quotation_requests')->nullOnDelete();
            $table->string('attachment_path')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_admin_message_at')->nullable();
            $table->string('machinery')->nullable();
            $table->string('machine_brand')->nullable();
            $table->string('machine_model')->nullable();
            $table->string('part_code')->nullable();
            $table->unsignedInteger('request_quantity')->nullable();
            $table->string('request_urgency', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->longText('message');
            $table->string('attachment_path')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notes');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_sequences');
    }
};
