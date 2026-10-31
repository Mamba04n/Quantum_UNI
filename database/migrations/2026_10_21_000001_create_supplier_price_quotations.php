<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_price_quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->foreignId('supplier_id')->constrained('entities')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->date('quoted_at')->index();
            $table->date('valid_until')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'quoted_at']);
        });
        Schema::create('supplier_price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_price_quotation_id')->constrained()->cascadeOnDelete();
            $table->string('part_number', 100)->index();
            $table->string('description', 1000);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('total_price', 18, 2);
            $table->string('availability', 20)->default('pending');
            $table->unsignedInteger('availability_version')->default(0);
            $table->text('availability_notes')->nullable();
            $table->timestamp('availability_checked_at')->nullable();
            $table->foreignId('availability_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_items');
        Schema::dropIfExists('supplier_price_quotations');
    }
};
