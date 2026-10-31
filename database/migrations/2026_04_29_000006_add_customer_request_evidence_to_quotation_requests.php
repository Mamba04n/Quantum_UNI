<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            $table->string('source_channel', 40)->nullable()->after('department');
            $table->string('evidence_attachment_path')->nullable()->after('source_channel');
            $table->string('machinery')->nullable()->after('evidence_attachment_path');
            $table->string('machine_brand')->nullable()->after('machinery');
            $table->string('machine_model')->nullable()->after('machine_brand');
            $table->string('part_code')->nullable()->after('machine_model');
            $table->unsignedInteger('request_quantity')->nullable()->after('part_code');
            $table->string('request_urgency', 20)->nullable()->after('request_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            $table->dropColumn([
                'source_channel',
                'evidence_attachment_path',
                'machinery',
                'machine_brand',
                'machine_model',
                'part_code',
                'request_quantity',
                'request_urgency',
            ]);
        });
    }
};
