<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table): void {
            $table->string('quote_to_ruc', 50)->nullable()->after('quote_to_email_alt');
            $table->string('customer_contact_name')->nullable()->after('quote_to_ruc');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table): void {
            $table->dropColumn(['quote_to_ruc', 'customer_contact_name']);
        });
    }
};
