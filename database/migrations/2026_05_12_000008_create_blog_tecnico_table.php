<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_tecnico')) {
            return;
        }

        Schema::create('blog_tecnico', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumen')->nullable();
            $table->string('categoria')->nullable();
            $table->string('maquina')->nullable();
            $table->string('modelo')->nullable();
            $table->string('pieza')->nullable();
            $table->string('numero_parte')->nullable();
            $table->longText('contenido');
            $table->string('imagen', 5000)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('categoria');
            $table->index('maquina');
            $table->index('numero_parte');
            $table->index('activo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_tecnico');
    }
};
