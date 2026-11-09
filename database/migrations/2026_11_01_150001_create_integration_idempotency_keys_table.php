<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_client_id')->constrained('integration_clients')->cascadeOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->string('operation', 50);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_payload')->nullable();
            $table->string('resource_type', 50)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamps();

            $table->unique(['integration_client_id', 'idempotency_key'], 'uniq_client_idempotency_key');
            $table->index(['integration_client_id', 'operation'], 'idx_idemp_client_op');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_idempotency_keys');
    }
};
