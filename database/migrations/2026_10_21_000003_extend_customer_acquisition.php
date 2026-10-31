<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecting_campaigns', function (Blueprint $t) {
            $t->id();
            $t->uuid('submission_token')->unique();
            $t->string('name', 180);
            $t->string('name_fingerprint', 64);
            $t->text('description')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['created_by', 'name_fingerprint']);
        });
        Schema::table('crm_prospects', function (Blueprint $t) {
            $t->foreignId('campaign_id')->nullable()->constrained('prospecting_campaigns')->restrictOnDelete();
            $t->json('context')->nullable();
            $t->json('classification_criteria')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_prospects', function (Blueprint $t) {
            $t->dropConstrainedForeignId('campaign_id');
            $t->dropColumn(['context', 'classification_criteria']);
        });
        Schema::dropIfExists('prospecting_campaigns');
    }
};
