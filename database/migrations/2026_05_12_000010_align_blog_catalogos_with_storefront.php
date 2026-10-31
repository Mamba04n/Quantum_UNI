<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_tecnico')) {
            Schema::table('blog_tecnico', function (Blueprint $table) {
                if (! Schema::hasColumn('blog_tecnico', 'activo')) {
                    $table->boolean('activo')->default(true)->after('imagen')->index();
                }
            });
        }

        if (Schema::hasTable('catalogos')) {
            Schema::table('catalogos', function (Blueprint $table) {
                if (! Schema::hasColumn('catalogos', 'descripcion')) {
                    $table->text('descripcion')->nullable()->after('titulo');
                }

                if (! Schema::hasColumn('catalogos', 'marca_id')) {
                    $table->unsignedBigInteger('marca_id')->nullable()->after('categoria')->index();
                }

                if (! Schema::hasColumn('catalogos', 'activo')) {
                    $table->boolean('activo')->default(true)->after('destacado')->index();
                }

                if (! Schema::hasColumn('catalogos', 'requiere_login')) {
                    $table->boolean('requiere_login')->default(false)->after('activo');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('catalogos')) {
            Schema::table('catalogos', function (Blueprint $table) {
                foreach (['descripcion', 'marca_id', 'activo', 'requiere_login'] as $column) {
                    if (Schema::hasColumn('catalogos', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('blog_tecnico') && Schema::hasColumn('blog_tecnico', 'activo')) {
            Schema::table('blog_tecnico', function (Blueprint $table) {
                $table->dropColumn('activo');
            });
        }
    }
};
