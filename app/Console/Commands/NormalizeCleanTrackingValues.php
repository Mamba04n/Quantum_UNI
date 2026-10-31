<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeCleanTrackingValues extends Command
{
    protected $signature = 'normalization:clean-tracking-values
                            {--dry-run : Muestra los registros a afectar sin realizar cambios}
                            {--chunk=500 : Tamaño del bloque}
                            {--force : Fuerza la ejecución sin confirmación}';

    protected $description = 'Limpia comillas y espacios en valores de estados de seguimiento (Fase 4)';

    public function handle()
    {
        $this->info('Iniciando limpieza de valores de seguimiento...');
        $dryRun = $this->option('dry-run');

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Esto modificará la base de datos. ¿Deseas continuar?')) {
                return;
            }
        }

        $tables = [
            'order_statuses' => ['administrative_phase', 'administrative_status'],
            'order_trackers' => ['operational_phase', 'operational_status']
        ];

        foreach ($tables as $table => $columns) {
            if (!DB::getSchemaBuilder()->hasTable($table)) continue;
            
            foreach ($columns as $column) {
                if (!DB::getSchemaBuilder()->hasColumn($table, $column)) continue;

                $this->info("Analizando {$table}.{$column}...");
                
                $items = DB::table($table)->get();
                $affected = 0;
                
                foreach ($items as $item) {
                    $original = $item->$column;
                    if ($original === null) continue;

                    // Limpiar comillas simples, dobles y espacios en extremos
                    $cleaned = trim(str_replace(['\'', '"'], '', $original));
                    $cleaned = str_replace(' ', '_', strtolower($cleaned)); // espacios internos a guion bajo

                    if ($original !== $cleaned) {
                        $this->info(" - Corrigiendo ID {$item->id}: [{$original}] -> [{$cleaned}]");
                        if (!$dryRun) {
                            DB::table($table)->where('id', $item->id)->update([$column => $cleaned]);
                        }
                        $affected++;
                    }
                }
                $this->info("Total corregidos en {$column}: {$affected}");
            }
        }
        
        $this->info('Limpieza finalizada.');
    }
}
