<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeInspectOrphans extends Command
{
    protected $signature = 'normalization:inspect-orphans';
    protected $description = 'Inspect and report orphan records for phase 18.1.1';

    public function handle()
    {
        $this->info('Inspecting orphans...');

        $relations = [
            ['table' => 'order_statuses', 'col' => 'sale_order_id', 'parent' => 'sale_orders', 'parent_col' => 'id'],
            ['table' => 'order_status_items', 'col' => 'sale_order_item_id', 'parent' => 'sale_order_items', 'parent_col' => 'id'],
            ['table' => 'order_status_items', 'col' => 'purchase_order_item_id', 'parent' => 'purchase_order_items', 'parent_col' => 'id'],
        ];

        foreach ($relations as $rel) {
            $orphans = DB::table($rel['table'])
                ->whereNotNull($rel['col'])
                ->whereNotIn($rel['col'], DB::table($rel['parent'])->pluck($rel['parent_col']))
                ->get();

            if ($orphans->count() > 0) {
                $this->warn("Found {$orphans->count()} orphans in {$rel['table']}.{$rel['col']}");
                foreach ($orphans as $orphan) {
                    $this->line(json_encode($orphan, JSON_PRETTY_PRINT));
                    // Nullifying them or just reporting? The prompt says:
                    // "No se corrigen silenciosamente. Los reconstruidos conservan información. Las FK pueden crearse después de la limpieza."
                    // Let's just set them to NULL if it's safe, but the prompt says to report and investigate.
                    // For now, let's just make the FK nullable and null them out so we can apply the FK, 
                    // or just leave them. The prompt says "Si es indeterminado no modificarlo... Generar un reporte".
                }
            } else {
                $this->info("No orphans in {$rel['table']}.{$rel['col']}");
            }
        }
    }
}
