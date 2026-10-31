<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'legal_name')) {
                $table->string('legal_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('entities', 'country')) {
                $table->string('country')->nullable()->after('rfc_ruc');
            }
            if (! Schema::hasColumn('entities', 'department')) {
                $table->string('department')->nullable()->after('country');
            }
            if (! Schema::hasColumn('entities', 'municipality')) {
                $table->string('municipality')->nullable()->after('department');
            }
            if (! Schema::hasColumn('entities', 'city')) {
                $table->string('city')->nullable()->after('municipality');
            }
            if (! Schema::hasColumn('entities', 'exact_address')) {
                $table->text('exact_address')->nullable()->after('city');
            }
            if (! Schema::hasColumn('entities', 'address')) {
                $table->text('address')->nullable()->after('exact_address');
            }
            if (! Schema::hasColumn('entities', 'website')) {
                $table->string('website')->nullable()->after('phone_home');
            }
            if (! Schema::hasColumn('entities', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('website');
            }
            if (! Schema::hasColumn('entities', 'contact_position')) {
                $table->string('contact_position')->nullable()->after('contact_name');
            }
            if (! Schema::hasColumn('entities', 'whatsapp')) {
                $table->string('whatsapp')->nullable()->after('contact_position');
            }
            if (! Schema::hasColumn('entities', 'status')) {
                $table->string('status', 20)->default('activo')->after('whatsapp');
            }
        });

        Schema::create('supplier_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['entity_id', 'name']);
        });

        Schema::create('supplier_product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('product_type');
            $table->timestamps();
            $table->unique(['entity_id', 'product_type']);
        });

        Schema::create('supplier_machine_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('machine_type');
            $table->timestamps();
            $table->unique(['entity_id', 'machine_type']);
        });

        Schema::create('supplier_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('payment_method');
            $table->timestamps();
            $table->unique(['entity_id', 'payment_method']);
        });

        Schema::create('supplier_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_holder');
            $table->string('account_number');
            $table->string('account_type')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('swift_iban')->nullable();
            $table->string('bank_country')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('client_machines', function (Blueprint $table) {
            if (! Schema::hasColumn('client_machines', 'machine_type')) {
                $table->string('machine_type')->nullable()->after('entity_id');
            }
            if (! Schema::hasColumn('client_machines', 'internal_code')) {
                $table->string('internal_code')->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('client_machines', 'hourmeter')) {
                $table->unsignedInteger('hourmeter')->nullable()->after('year');
            }
            if (! Schema::hasColumn('client_machines', 'hourmeter_date')) {
                $table->date('hourmeter_date')->nullable()->after('hourmeter');
            }
            if (! Schema::hasColumn('client_machines', 'status')) {
                $table->string('status', 30)->default('activa')->after('hourmeter_date');
            }
            if (! Schema::hasColumn('client_machines', 'image_path')) {
                $table->string('image_path')->nullable()->after('status');
            }
            if (! Schema::hasColumn('client_machines', 'notes')) {
                $table->text('notes')->nullable()->after('image_path');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'client_machine_id')) {
                $table->foreignId('client_machine_id')->nullable()->after('customer_id')->constrained('client_machines')->nullOnDelete();
            }
        });

        Schema::table('quotation_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_requests', 'client_machine_id')) {
                $table->foreignId('client_machine_id')->nullable()->after('user_id')->constrained('client_machines')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotation_requests', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_requests', 'client_machine_id')) {
                $table->dropConstrainedForeignId('client_machine_id');
            }
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'client_machine_id')) {
                $table->dropConstrainedForeignId('client_machine_id');
            }
        });

        Schema::dropIfExists('supplier_bank_accounts');
        Schema::dropIfExists('supplier_payment_methods');
        Schema::dropIfExists('supplier_machine_types');
        Schema::dropIfExists('supplier_product_types');
        Schema::dropIfExists('supplier_brands');
    }
};
