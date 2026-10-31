<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeSyncProductImages extends Command
{
    protected $signature = 'normalization:sync-product-images {--dry-run}';
    protected $description = 'Sync products.image_path to product_images and set as primary';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $this->info('Syncing product images...' . ($dryRun ? ' (DRY RUN)' : ''));

        $products = DB::table('products')->whereNotNull('image_path')->get();
        $synced = 0;

        foreach ($products as $product) {
            $exists = DB::table('product_images')->where([
                'product_id' => $product->id,
                'image_path' => $product->image_path
            ])->exists();

            if (!$exists) {
                if (!$dryRun) {
                    DB::table('product_images')->insert([
                        'product_id' => $product->id,
                        'image_path' => $product->image_path,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $synced++;
                $this->line("Synced image {$product->image_path} for product {$product->id}");
            }
        }

        $this->info("Synced $synced images to product_images.");
    }
}
