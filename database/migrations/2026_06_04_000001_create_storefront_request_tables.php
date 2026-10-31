<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mensajes')) {
            Schema::create('mensajes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->text('mensaje')->nullable();
                $table->timestamp('fecha')->nullable();
                $table->boolean('leido')->default(false);
            });
        }

        if (! Schema::hasTable('solicitudes_servicio')) {
            Schema::create('solicitudes_servicio', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('ubicacion', 200)->nullable();
                $table->string('maquinaria', 200)->nullable();
                $table->text('descripcion')->nullable();
                $table->string('imagen')->nullable();
                $table->timestamp('fecha')->nullable();
                $table->string('estado', 30)->default('pendiente');
            });
        }

        if (! Schema::hasTable('pedidos_web')) {
            Schema::create('pedidos_web', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 40)->unique();
                $table->string('guest_token', 64);
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('canal', 40)->default('web_api');
                $table->string('estado', 30)->default('nuevo');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('moneda', 10)->default('USD');
                $table->string('payment_method', 30)->nullable();
                $table->string('paypal_order_id', 80)->nullable();
                $table->string('paypal_capture_id', 80)->nullable();
                $table->string('payment_status', 30)->nullable();
                $table->json('raw_response')->nullable();
                $table->string('whatsapp_destino', 25)->nullable();
                $table->text('whatsapp_url')->nullable();
                $table->timestamp('creado_en')->nullable();
                $table->timestamp('actualizado_en')->nullable();

                $table->index('guest_token');
                $table->index('ip');
                $table->index('estado');
                $table->index('paypal_order_id');
                $table->index('payment_status');
            });
        } else {
            Schema::table('pedidos_web', function (Blueprint $table) {
                if (! Schema::hasColumn('pedidos_web', 'payment_method')) {
                    $table->string('payment_method', 30)->nullable()->after('moneda');
                }
                if (! Schema::hasColumn('pedidos_web', 'paypal_order_id')) {
                    $table->string('paypal_order_id', 80)->nullable()->after('payment_method')->index();
                }
                if (! Schema::hasColumn('pedidos_web', 'paypal_capture_id')) {
                    $table->string('paypal_capture_id', 80)->nullable()->after('paypal_order_id');
                }
                if (! Schema::hasColumn('pedidos_web', 'payment_status')) {
                    $table->string('payment_status', 30)->nullable()->after('paypal_capture_id')->index();
                }
                if (! Schema::hasColumn('pedidos_web', 'raw_response')) {
                    $table->json('raw_response')->nullable()->after('payment_status');
                }
            });
        }

        if (! Schema::hasTable('pedidos_web_items')) {
            Schema::create('pedidos_web_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_id')->constrained('pedidos_web')->cascadeOnDelete();
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('nombre', 220);
                $table->string('sku', 80)->nullable();
                $table->unsignedInteger('cantidad')->default(1);
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->text('imagen_url')->nullable();
                $table->text('detalle_url')->nullable();
                $table->timestamp('creado_en')->nullable();

                $table->index('producto_id');
            });
        }
    }

    public function down(): void
    {
        // Intentionally conservative: these tables may have been created by
        // the legacy storefront before Laravel owned this migration.
    }
};
