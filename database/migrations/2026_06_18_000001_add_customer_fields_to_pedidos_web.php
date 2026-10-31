<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_web', function (Blueprint $table) {
            $table->string('nombre_cliente')->nullable()->after('estado');
            $table->string('email_cliente')->nullable()->after('nombre_cliente');
            $table->string('telefono_cliente', 30)->nullable()->after('email_cliente');
            $table->string('empresa_cliente')->nullable()->after('telefono_cliente');
            $table->text('notas_cliente')->nullable()->after('empresa_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_web', fn (Blueprint $table) => $table->dropColumn([
            'nombre_cliente', 'email_cliente', 'telefono_cliente', 'empresa_cliente', 'notas_cliente',
        ]));
    }
};
