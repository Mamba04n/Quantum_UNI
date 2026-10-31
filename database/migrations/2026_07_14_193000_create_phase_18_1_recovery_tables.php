<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Brands
        if (!Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('brand_entity')) {
            Schema::create('brand_entity', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->unique(['brand_id', 'entity_id']);
                $table->timestamps();
            });
        }

        // 2. Product Types
        if (!Schema::hasTable('product_types')) {
            Schema::create('product_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('entity_product_type')) {
            Schema::create('entity_product_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
                $table->unique(['entity_id', 'product_type_id']);
                $table->timestamps();
            });
        }

        // 3. Machine Types
        if (!Schema::hasTable('machine_types')) {
            Schema::create('machine_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('entity_machine_type')) {
            Schema::create('entity_machine_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('machine_type_id')->constrained()->cascadeOnDelete();
                $table->unique(['entity_id', 'machine_type_id']);
                $table->timestamps();
            });
        }

        // 4. Payment Methods
        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('entity_payment_method')) {
            Schema::create('entity_payment_method', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
                $table->unique(['entity_id', 'payment_method_id']);
                $table->timestamps();
            });
        }

        // 5. Product Images
        if (!Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('image_path');
                $table->string('alt_text')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'image_path']);
            });
        }
        
        // 6. Bank Accounts
        if (!Schema::hasTable('entity_bank_accounts')) {
            Schema::create('entity_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->string('bank_name');
                $table->string('account_holder')->nullable();
                $table->string('account_number');
                $table->string('account_type')->nullable();
                $table->string('currency', 3)->default('MXN');
                $table->string('swift')->nullable();
                $table->string('iban')->nullable();
                $table->string('country')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_bank_accounts');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('entity_payment_method');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('entity_machine_type');
        Schema::dropIfExists('machine_types');
        Schema::dropIfExists('entity_product_type');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('brand_entity');
        Schema::dropIfExists('brands');
    }
};
