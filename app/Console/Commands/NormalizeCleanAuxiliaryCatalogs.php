<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NormalizeCleanAuxiliaryCatalogs extends Command
{
    protected $signature = 'normalization:clean-auxiliary-catalogs
                            {--dry-run : Muestra los registros a afectar sin realizar cambios}
                            {--chunk=500 : Tamaño del bloque de procesamiento}
                            {--force : Fuerza la ejecución sin confirmación}';

    protected $description = 'Limpia registros auxiliares y arregla slugs (Unknown, Temporal, N/A) de catálogos';

    public function handle()
    {
        $this->info('Iniciando limpieza de catálogos auxiliares (Fase 9)...');
        $dryRun = $this->option('dry-run');

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Esto modificará la base de datos. ¿Deseas continuar?')) {
                return;
            }
        }

        $catalogs = [
            'brands' => [
                'relations' => [
                    ['table' => 'products', 'column' => 'brand_id'],
                    ['table' => 'client_machines', 'column' => 'brand_id'],
                ]
            ],
            'product_types' => [
                'relations' => [
                    ['table' => 'products', 'column' => 'product_type_id'],
                ]
            ],
            'machine_types' => [
                'relations' => [
                    ['table' => 'client_machines', 'column' => 'machine_type_id'],
                ]
            ],
            'payment_methods' => [
                'relations' => [
                    ['table' => 'sale_orders', 'column' => 'payment_method_id'],
                ]
            ],
        ];

        $keywords = ['unknown', 'sin especificar', 'n/a', 'temporal', 'migrated'];

        $totalAffected = 0;
        $totalSlugsFixed = 0;

        foreach ($catalogs as $catalog => $config) {
            if (!DB::getSchemaBuilder()->hasTable($catalog)) {
                $this->warn("La tabla {$catalog} no existe, saltando...");
                continue;
            }

            $this->info("\n--- Analizando tabla: {$catalog} ---");

            // 1. Limpieza de registros basura
            $query = DB::table($catalog)->where(function($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhere('name', 'LIKE', "%{$word}%");
                }
            });

            $garbage = $query->get();
            $this->info("Encontrados " . $garbage->count() . " registros candidatos a basura.");

            foreach ($garbage as $item) {
                $inUse = false;
                foreach ($config['relations'] as $relation) {
                    if (DB::getSchemaBuilder()->hasTable($relation['table']) && DB::getSchemaBuilder()->hasColumn($relation['table'], $relation['column'])) {
                        $count = DB::table($relation['table'])->where($relation['column'], $item->id)->count();
                        if ($count > 0) {
                            $inUse = true;
                            $this->warn(" - [Omitido] {$item->name} (ID: {$item->id}) está en uso en {$relation['table']} ($count registros).");
                            break;
                        }
                    }
                }

                if (!$inUse) {
                    $this->info(" - [A Eliminar] {$item->name} (ID: {$item->id})");
                    if (!$dryRun) {
                        DB::table($catalog)->where('id', $item->id)->delete();
                        $totalAffected++;
                    }
                }
            }

            // 2. Limpieza de slugs
            if (DB::getSchemaBuilder()->hasColumn($catalog, 'slug')) {
                $items = DB::table($catalog)->get();
                foreach ($items as $item) {
                    if (!isset($item->name)) continue;
                    $expectedSlug = Str::slug($item->name);
                    
                    // Si el slug actual tiene un hash al final (ej. volvo-ce-6a568a73712d3)
                    if ($item->slug !== $expectedSlug) {
                        // Ver si el expectedSlug ya está en uso
                        $exists = DB::table($catalog)->where('slug', $expectedSlug)->where('id', '!=', $item->id)->exists();
                        if (!$exists) {
                            $this->info(" - [Slug Arreglado] {$item->slug} -> {$expectedSlug}");
                            if (!$dryRun) {
                                DB::table($catalog)->where('id', $item->id)->update(['slug' => $expectedSlug]);
                                $totalSlugsFixed++;
                            }
                        } else {
                            $this->warn(" - [Slug Conflicto] No se puede cambiar {$item->slug} a {$expectedSlug} porque ya existe.");
                        }
                    }
                }
            }
        }

        $this->info("\nResumen:");
        $this->info("- Registros basura eliminados: {$totalAffected}");
        $this->info("- Slugs normalizados: {$totalSlugsFixed}");
        if ($dryRun) {
            $this->warn("(DRY RUN: No se aplicaron cambios reales)");
        }
    }
}
