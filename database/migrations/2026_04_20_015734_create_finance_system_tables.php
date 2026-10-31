<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Directorio Unificado de Entidades (Clientes, Proveedores, Socios)
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cliente', 'proveedor', 'socio']);
            $table->string('contact_info')->nullable();
            $table->string('rfc_ruc')->nullable();
            $table->timestamps();
        });

        // 2. Cuentas por Cobrar (CXC - Lo que le deben a tu empresa)
        Schema::create('account_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->onDelete('cascade');
            $table->string('concept'); // Ej: Factura #123
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->enum('status', ['pendiente', 'parcial', 'pagado'])->default('pendiente');
            $table->timestamps();
        });

        // 3. Cuentas por Pagar (CXP - Lo que tu empresa debe a socios o proveedores)
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->onDelete('cascade');
            $table->string('concept'); // Ej: Préstamo de Socio para capital, o Compra a Proveedor
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->enum('status', ['pendiente', 'parcial', 'pagado'])->default('pendiente');
            $table->timestamps();
        });

        // 4. Historial de Pagos y Abonos (Aplica para CXC y CXP)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // Permite vincularse tanto a CXC (cobros) como CXP (abonos)
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable(); // Transferencia, Efectivo, Cheque
            $table->string('receipt_reference')->nullable(); // Referencia bancaria o recibo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('account_payables');
        Schema::dropIfExists('account_receivables');
        Schema::dropIfExists('entities');
    }
};
