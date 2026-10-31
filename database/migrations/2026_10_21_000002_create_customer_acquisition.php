<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_prospects', function (Blueprint $t) {
            $t->id();
            $t->uuid('submission_token')->unique();
            $t->string('name');
            $t->string('source', 60);
            $t->string('stage', 20)->default('prospectos')->index();
            $t->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('entity_id')->nullable()->constrained('entities')->restrictOnDelete();
            foreach (['ruc', 'city', 'sector', 'website', 'email', 'phone', 'classification'] as $field) {
                $t->string($field)->nullable();
            }
            foreach (['comment', 'need', 'products', 'services', 'next_action', 'last_result', 'discard_reason'] as $field) {
                $t->text($field)->nullable();
            }
            $t->json('contacts')->nullable();
            $t->json('machines')->nullable();
            $t->timestamp('last_contact_at')->nullable();
            $t->string('last_contact_channel', 40)->nullable();
            $t->date('next_contact_at')->nullable()->index();
            $t->timestamp('discarded_at')->nullable()->index();
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
            $t->index(['responsible_id', 'stage']);
        });
        Schema::create('commercial_opportunities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prospect_id')->unique()->constrained('crm_prospects')->restrictOnDelete();
            $t->foreignId('entity_id')->constrained('entities')->restrictOnDelete();
            $t->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('title');
            $t->text('need')->nullable();
            $t->json('snapshot');
            $t->timestamps();
        });
        // Serializes identity checks when converting or registering prospects.
        Schema::create('crm_identity_locks', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('crm_identity_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_opportunities');
        Schema::dropIfExists('crm_prospects');
        Schema::dropIfExists('crm_identity_locks');
    }
};
