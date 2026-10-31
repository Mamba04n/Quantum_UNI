<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sku_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 10);
            $table->string('brand_code', 3);
            $table->string('category_code', 3);
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['company_code', 'brand_code', 'category_code'], 'product_sku_sequences_prefix_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sku_sequences');
    }
};
