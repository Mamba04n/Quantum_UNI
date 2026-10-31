<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalogos')) {
            return;
        }

        Schema::create('catalogos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('titulo')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('archivo', 500);
            $table->string('categoria')->nullable();
            $table->unsignedBigInteger('marca_id')->nullable();
            $table->boolean('destacado')->default(false);
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_login')->default(false);
            $table->timestamp('fecha_subida')->nullable();
            $table->timestamps();

            $table->index('categoria');
            $table->index('marca_id');
            $table->index('destacado');
            $table->index('activo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogos');
    }
};
