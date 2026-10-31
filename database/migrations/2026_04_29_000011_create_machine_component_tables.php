<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_machine_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_machine_id')->constrained('client_machines')->cascadeOnDelete();
            $table->string('component_name');
            $table->string('part_number');
            $table->string('serial_number')->nullable();
            $table->string('component_model')->nullable();
            $table->string('component_brand')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('counter_type', 30);
            $table->unsignedInteger('current_usage')->default(0);
            $table->unsignedInteger('estimated_life');
            $table->integer('residual_life')->default(0);
            $table->decimal('consumed_percentage', 6, 2)->default(0);
            $table->date('last_installation_date')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->string('status', 30)->default('bueno');
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_machine_id', 'status']);
            $table->index(['part_number', 'serial_number']);
        });

        Schema::create('component_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('customer_machine_components')->cascadeOnDelete();
            $table->unsignedInteger('previous_usage')->default(0);
            $table->unsignedInteger('new_usage')->default(0);
            $table->string('counter_type', 30);
            $table->integer('previous_residual_life')->default(0);
            $table->integer('new_residual_life')->default(0);
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['component_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_usage_logs');
        Schema::dropIfExists('customer_machine_components');
    }
};
