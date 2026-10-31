<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeDeduplicateImages extends Command
{
    protected $signature = 'normalization:deduplicate-images';
    protected $description = 'Deduplica imágenes de producto y garantiza is_primary único por producto';

    public function handle()
    {
        $this->info('Iniciando deduplicación de imágenes...');

        if (!DB::getSchemaBuilder()->hasTable('product_images')) {
            $this->error('Tabla product_images no existe.');
            return;
        }

        // 1. Eliminar huérfanos que hayan quedado
        DB::statement('DELETE FROM product_images WHERE product_id NOT IN (SELECT id FROM products)');

        // 2. Resolver duplicados lógicos (ej: prod_2.png vs products/prod_2.png)
        // Eliminamos aquellos que tengan el prefijo 'products/' si ya existe el archivo base (o viceversa)
        // Para simplificar, obtenemos todos y limpiamos.
        $images = DB::table('product_images')->get();
        
        $kept = [];
        $deleted = 0;

        foreach ($images as $img) {
            $cleanName = basename($img->image_path);
            $key = $img->product_id . '_' . $cleanName;

            if (isset($kept[$key])) {
                // Duplicado encontrado
                DB::table('product_images')->where('id', $img->id)->delete();
                $deleted++;
                $this->line("Eliminado duplicado: ID {$img->id} ({$img->image_path})");
            } else {
                $kept[$key] = true;
                // Si la ruta no empieza con products/, lo normalizamos (asumiendo que deberían estar en products/)
                // No es estrictamente necesario normalizar la ruta aquí, solo asegurarnos que no haya 2.
            }
        }

        $this->info("Imágenes duplicadas eliminadas: {$deleted}");

        // 3. Garantizar que cada producto tenga EXACTAMENTE UNA imagen is_primary = 1
        $this->info('Garantizando un único is_primary por producto...');
        $products = DB::table('products')->pluck('id');
        $fixedPrimaries = 0;

        foreach ($products as $productId) {
            $prodImages = DB::table('product_images')
                ->where('product_id', $productId)
                ->orderBy('id', 'asc') // Tomaremos el más viejo como primario por defecto
                ->get();

            if ($prodImages->isEmpty()) {
                continue;
            }

            // Poner todos en 0 primero
            DB::table('product_images')->where('product_id', $productId)->update(['is_primary' => 0]);

            // Poner el primero (o el que ya era primary) como primary
            $primaryToSet = $prodImages->firstWhere('is_primary', 1) ?: $prodImages->first();
            
            DB::table('product_images')
                ->where('id', $primaryToSet->id)
                ->update(['is_primary' => 1]);
                
            $fixedPrimaries++;
        }

        $this->info("Productos con is_primary garantizado: {$fixedPrimaries}");
        
        // 4. Asegurar que products.image_path sea el mismo que el is_primary = 1 o NULL si no tiene
        DB::statement('
            UPDATE products p
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
            SET p.image_path = pi.image_path
        ');
        $this->info("products.image_path resincronizado.");

        $this->info('Deduplicación finalizada con éxito.');
    }
}
