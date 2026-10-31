<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogAndCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $blogImages = [
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200&q=80',
            'https://images.unsplash.com/photo-1530124566582-a618bc2615dc?w=1200&q=80',
            'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?w=1200&q=80',
            'https://images.unsplash.com/photo-1535924765662-799ff05eb2c9?w=1200&q=80',
            'https://images.unsplash.com/photo-1496247749665-49cf5b1022e9?w=1200&q=80',
            'https://images.unsplash.com/photo-1590481223405-1a84f346f047?w=1200&q=80',
            'https://images.unsplash.com/photo-1563806229348-7d40e1b3ed9f?w=1200&q=80',
            'https://images.unsplash.com/photo-1581092334651-ddf26d9a09d0?w=1200&q=80',
            'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=1200&q=80',
            'https://images.unsplash.com/photo-1505322022379-7c3353ee6291?w=1200&q=80'
        ];

        // 10 Blogs
        for ($i = 1; $i <= 10; $i++) {
            $titulo = "Mantenimiento Preventivo de Maquinaria Industrial - Caso de Estudio $i";
            DB::table('blog_tecnico')->insert([
                'titulo' => $titulo,
                'slug' => Str::slug($titulo) . '-' . Str::random(5),
                'resumen' => "Un análisis profundo sobre las mejores prácticas y técnicas de mantenimiento para la maquinaria moderna, garantizando una vida útil prolongada. Ejercicio práctico $i.",
                'categoria' => "Mantenimiento",
                'maquina' => "Excavadora Volvo EC210",
                'modelo' => "EC210-$i",
                'pieza' => "Bomba Hidráulica",
                'numero_parte' => "VH-" . rand(1000, 9999) . "-$i",
                'contenido' => "<h2>Introducción al Mantenimiento</h2><p>El mantenimiento preventivo es crucial para cualquier operación industrial. En este artículo, abordaremos la importancia de las rutinas periódicas y cómo implementarlas de manera eficiente.</p><h3>Puntos clave:</h3><ul><li>Revisión de fluidos.</li><li>Calibración de piezas móviles.</li><li>Inspección de desgaste.</li></ul><p>Este es el artículo $i generado automáticamente para ilustrar el sistema de blog técnico, incluyendo todos los campos llenos según lo solicitado.</p>",
                'imagen' => $blogImages[$i - 1],
                'activo' => 1,
                'created_at' => $now->copy()->subDays($i),
                'updated_at' => $now->copy()->subDays($i),
            ]);
        }

        // 10 Catalogos
        for ($i = 1; $i <= 10; $i++) {
            DB::table('catalogos')->insert([
                'nombre' => "Catalogo de Repuestos Volvo 202$i",
                'titulo' => "Catálogo General de Partes Volvo - Volumen $i",
                'descripcion' => "Este catálogo contiene todas las especificaciones técnicas, números de parte y diagramas de despiece para maquinaria pesada de la serie $i. Ideal para talleres y distribuidores.",
                'archivo' => "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf",
                'categoria' => "Repuestos Pesados",
                'marca_id' => null, // Dejar nulo a menos que haya una tabla marcas poblada
                'destacado' => ($i <= 3) ? 1 : 0, // Destacar los primeros 3
                'activo' => 1,
                'requiere_login' => 0,
                'fecha_subida' => $now->copy()->subDays($i),
                'created_at' => $now->copy()->subDays($i),
                'updated_at' => $now->copy()->subDays($i),
            ]);
        }
    }
}
