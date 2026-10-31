<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class NormalizeMigrateProductImages extends Command
{
    protected $signature = 'normalization:migrate-product-images';
    protected $description = 'Migrate lost product images from erp_legacy into product_images';

    public function handle()
    {
        $this->info('Starting Product Images Restoration...');

        try {
            $legacy = new PDO('mysql:host=127.0.0.1;dbname=erp_legacy;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (\Exception $e) {
            $this->error('Could not connect to erp_legacy: ' . $e->getMessage());
            return;
        }

        $stmt = $legacy->query("SELECT * FROM productos_imagenes");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $row) {
            // Check if product exists in current DB
            // 'producto_id' mapped to 'product_id'
            $productId = $row['producto_id'] ?? null;

            if ($productId && DB::table('products')->where('id', $productId)->exists()) {
                $exists = DB::table('product_images')->where([
                    'product_id' => $productId,
                    'image_path' => $row['imagen']
                ])->exists();

                if (!$exists) {
                    DB::table('product_images')->insert([
                        'product_id' => $productId,
                        'image_path' => $row['imagen'],
                        'is_primary' => $row['principal'] ?? false,
                        'sort_order' => $row['orden'] ?? 0,
                        'created_at' => $row['created_at'] ?? now(),
                        'updated_at' => $row['updated_at'] ?? now(),
                    ]);
                    $count++;
                }
            }
        }

        $this->info("Restored $count product images.");
    }
}
