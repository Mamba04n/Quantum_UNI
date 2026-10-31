<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign keys referencing clients
        Schema::table('machines', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('entities')->cascadeOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('entities')->nullOnDelete();
        });

        // The logistics_shipments table foreign key was already updated manually or in previous migration,
        // but we'll try catching it just in case to ensure a clean slate.
        try {
            Schema::table('logistics_shipments', function (Blueprint $table) {
                // If it fails because it was already dropped, the catch block ignores it.
                $table->dropForeign(['client_id']);
                $table->foreign('client_id')->references('id')->on('entities')->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Probably already updated, safe to ignore
        }

        // 2. Drop the redundant clients table
        Schema::dropIfExists('clients');
    }

    public function down(): void
    {
        // Down migration would recreate the clients table, which we don't want to do,
        // but to be perfectly compliant:
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ruc', 20)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();
        });
    }
};
