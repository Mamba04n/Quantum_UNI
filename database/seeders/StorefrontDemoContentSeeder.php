<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorefrontDemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        Storage::disk('public')->makeDirectory('storefront/catalogs');
        Storage::disk('public')->makeDirectory('storefront/blog');

        $catalogs = [
            ['file' => 'qs-demo-catalogo-filtros-hidraulica.pdf', 'title' => 'Catálogo técnico de filtros e hidráulica', 'cat' => 'Filtros e hidráulica', 'desc' => 'Referencias de filtros, sellos, mangueras y componentes hidráulicos para mantenimiento preventivo.'],
            ['file' => 'qs-demo-guia-sensores-electricos.pdf', 'title' => 'Guía de sensores y componentes eléctricos', 'cat' => 'Eléctrico y sensores', 'desc' => 'Criterios de revisión para sensores, arneses y componentes eléctricos en maquinaria agrícola e industrial.'],
            ['file' => 'qs-demo-manual-tren-motriz.pdf', 'title' => 'Manual de tren motriz y transmisión', 'cat' => 'Tren motriz', 'desc' => 'Guía de compatibilidad para piezas de transmisión, rodamientos y componentes de desgaste.'],
            ['file' => 'qs-demo-plan-mantenimiento-500h.pdf', 'title' => 'Plan de mantenimiento cada 500 horas', 'cat' => 'Mantenimiento', 'desc' => 'Checklist técnico para rutinas de mantenimiento por horas de trabajo y condición operativa.'],
            ['file' => 'qs-demo-compatibilidad-marcas.pdf', 'title' => 'Matriz de compatibilidad por marca y modelo', 'cat' => 'Compatibilidades', 'desc' => 'Referencia para preparar solicitudes de repuestos por marca, modelo, número de parte y aplicación.'],
        ];

        foreach ($catalogs as $index => $catalog) {
            Storage::disk('public')->put('storefront/catalogs/'.$catalog['file'], $this->demoPdf($catalog['title']));

            DB::table('catalogos')->updateOrInsert(
                ['archivo' => $catalog['file']],
                [
                    'nombre' => $catalog['title'],
                    'titulo' => $catalog['title'],
                    'descripcion' => $catalog['desc'],
                    'categoria' => $catalog['cat'],
                    'marca_id' => null,
                    'destacado' => $index < 2,
                    'activo' => true,
                    'requiere_login' => false,
                    'fecha_subida' => $now->copy()->subDays($index),
                    'created_at' => $now->copy()->subDays($index),
                    'updated_at' => $now,
                ]
            );
        }

        $this->copyBlogImage('hero-agro/tractor-field.webp', 'qs-demo-campo-maquinaria.webp');
        $this->copyBlogImage('modules/services-maintenance.jpg', 'qs-demo-hidraulica.jpg');
        $this->copyBlogImage('modules/products-spare-parts.jpg', 'qs-demo-componentes.jpg');
        $this->copyBlogImage('hero-agro/harvest-combine.webp', 'qs-demo-operacion.webp');
        $this->copyBlogImage('hero-agro/service-maintenance.webp', 'qs-demo-soporte.webp');

        $posts = [
            ['titulo' => 'Cómo identificar un filtro hidráulico antes de cotizar', 'categoria' => 'Identificación de repuestos', 'maquina' => 'Maquinaria agrícola', 'pieza' => 'Filtro hidráulico', 'parte' => 'QS-FH-500', 'img' => 'qs-demo-hidraulica.jpg', 'resumen' => 'Señales, medidas y datos mínimos para confirmar compatibilidad antes de comprar un filtro hidráulico.'],
            ['titulo' => 'Checklist rápido para mantenimiento de 500 horas', 'categoria' => 'Mantenimiento preventivo', 'maquina' => 'Equipo industrial', 'pieza' => 'Kit de mantenimiento', 'parte' => 'QS-MT-500H', 'img' => 'qs-demo-operacion.webp', 'resumen' => 'Una guía práctica para organizar inspecciones, fluidos, filtros y componentes críticos por horas de trabajo.'],
            ['titulo' => 'Cuándo revisar sensores eléctricos en maquinaria pesada', 'categoria' => 'Diagnóstico técnico', 'maquina' => 'Maquinaria pesada', 'pieza' => 'Sensor eléctrico', 'parte' => 'QS-SE-120', 'img' => 'qs-demo-componentes.jpg', 'resumen' => 'Síntomas frecuentes, pruebas iniciales y datos que ayudan a reducir diagnósticos imprecisos.'],
            ['titulo' => 'Compatibilidad de piezas: datos que debes enviar al proveedor', 'categoria' => 'Compatibilidad', 'maquina' => 'Agrícola e industrial', 'pieza' => 'Componentes varios', 'parte' => 'QS-COMP', 'img' => 'qs-demo-campo-maquinaria.webp', 'resumen' => 'Modelo, placa, número de parte, fotos y contexto de operación: qué enviar para recibir una respuesta útil.'],
            ['titulo' => 'Cómo reducir paros recurrentes con soporte técnico documentado', 'categoria' => 'Soporte técnico', 'maquina' => 'Flota mixta', 'pieza' => 'Sistema de mantenimiento', 'parte' => 'QS-ST-01', 'img' => 'qs-demo-soporte.webp', 'resumen' => 'Por qué documentar hallazgos, acciones y repuestos mejora la continuidad operativa.'],
        ];

        foreach ($posts as $index => $post) {
            DB::table('blog_tecnico')->updateOrInsert(
                ['slug' => 'qs-demo-'.Str::slug($post['titulo'])],
                [
                    'titulo' => $post['titulo'],
                    'resumen' => $post['resumen'],
                    'categoria' => $post['categoria'],
                    'maquina' => $post['maquina'],
                    'modelo' => 'Referencia demo',
                    'pieza' => $post['pieza'],
                    'numero_parte' => $post['parte'],
                    'contenido' => $this->blogContent($post['titulo'], $post['resumen']),
                    'imagen' => $post['img'],
                    'activo' => true,
                    'created_at' => $now->copy()->subDays($index),
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function copyBlogImage(string $source, string $target): void
    {
        $path = public_path('storefront/images/'.$source);

        if (is_file($path)) {
            Storage::disk('public')->put('storefront/blog/'.$target, file_get_contents($path));
        }
    }

    private function demoPdf(string $title): string
    {
        $safeTitle = str_replace(['(', ')', '\\'], '', $title);

        return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
            ."4 0 obj << /Length 170 >> stream\nBT /F1 22 Tf 72 720 Td (Quantum Soluciones) Tj 0 -36 Td /F1 14 Tf ({$safeTitle}) Tj 0 -30 Td /F1 11 Tf (Documento demo para visualizacion web.) Tj ET\nendstream endobj\n"
            ."5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";
    }

    private function blogContent(string $title, string $summary): string
    {
        return '<h2>'.$title.'</h2><p>'.$summary.'</p><p>En Quantum Soluciones validamos la información técnica antes de recomendar una pieza o servicio. Este contenido demo muestra cómo se vería una publicación real en el blog técnico.</p><h3>Datos recomendados</h3><ul><li>Marca, modelo y aplicación del equipo.</li><li>Número de parte, SKU o fotografía de la pieza.</li><li>Síntomas, horas de trabajo y urgencia operativa.</li></ul>';
    }
}
