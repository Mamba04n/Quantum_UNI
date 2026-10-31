<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ampliar account_receivables con columnas del Excel CXC
        Schema::table('account_receivables', function (Blueprint $table) {
            if (!Schema::hasColumn('account_receivables', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('id');
            }
            if (!Schema::hasColumn('account_receivables', 'classification')) {
                $table->string('classification')->nullable()->after('concept'); // Ej: "Venta de producto"
            }
            if (!Schema::hasColumn('account_receivables', 'condition')) {
                $table->string('condition')->nullable()->after('classification'); // Contado / 30 Días
            }
            if (!Schema::hasColumn('account_receivables', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('condition');
            }
            if (!Schema::hasColumn('account_receivables', 'amount_usd')) {
                $table->decimal('amount_usd', 14, 2)->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('account_receivables', 'retention_amount')) {
                $table->decimal('retention_amount', 14, 2)->default(0)->after('amount_usd');
            }
            if (!Schema::hasColumn('account_receivables', 'days_overdue')) {
                $table->integer('days_overdue')->default(0)->after('due_date');
            }
        });

        // Ampliar account_payables con columnas del Excel CXP
        Schema::table('account_payables', function (Blueprint $table) {
            if (!Schema::hasColumn('account_payables', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('id');
            }
            if (!Schema::hasColumn('account_payables', 'classification')) {
                $table->string('classification')->nullable()->after('concept');
            }
            if (!Schema::hasColumn('account_payables', 'condition')) {
                $table->string('condition')->nullable()->after('classification');
            }
            if (!Schema::hasColumn('account_payables', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('condition');
            }
            if (!Schema::hasColumn('account_payables', 'amount_usd')) {
                $table->decimal('amount_usd', 14, 2)->nullable()->after('total_amount');
            }
        });

        // Tabla de Facturas (módulo nuevo)
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->nullable(); // N° Factura
                $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->date('date');
                $table->string('cotizacion_ref')->nullable(); // N° Cotización
                $table->text('description');
                $table->decimal('amount_nio', 14, 2)->default(0);       // Monto C$
                $table->decimal('retention_amount', 14, 2)->default(0); // Monto menos retención
                $table->decimal('amount_usd', 14, 2)->nullable();        // Monto $
                $table->date('due_date')->nullable();
                $table->string('status')->default('Pendiente');           // Pagado / Pendiente
                $table->string('classification')->nullable();
                $table->string('condition')->default('Contado');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'classification', 'condition', 'invoice_date', 'amount_usd', 'retention_amount', 'days_overdue']);
        });
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'classification', 'condition', 'invoice_date', 'amount_usd']);
        });
        Schema::dropIfExists('invoices');
    }
};
