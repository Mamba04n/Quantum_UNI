<?php

namespace App\Domain\Inventario\Products\Services;

use App\Enums\TechnicalCategory;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSkuGenerator
{
    public function generate(Product $product): string
    {
        return DB::transaction(function () use ($product) {
            $companyCode = $this->segment(config('inventory.company_sku_prefix', 'QS'), 2);
            $brandCode = $this->segment((string) ($product->brand ?: 'XXX'));
            $categoryCode = $this->segment($this->categoryValue($product));

            DB::table('product_sku_sequences')->insertOrIgnore([
                'company_code' => $companyCode,
                'brand_code' => $brandCode,
                'category_code' => $categoryCode,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('product_sku_sequences')
                ->where('company_code', $companyCode)
                ->where('brand_code', $brandCode)
                ->where('category_code', $categoryCode)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;
            do {
                $sku = sprintf('%s-%s-%s-%04d', $companyCode, $brandCode, $categoryCode, $number);
                $number++;
            } while (Product::where('sku', $sku)->exists());

            DB::table('product_sku_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_number' => $number,
                    'updated_at' => now(),
                ]);

            ActivityLog::log('inventario', 'sku_generated', "SKU {$sku} generado automaticamente.", [
                'company_code' => $companyCode,
                'brand_code' => $brandCode,
                'category_code' => $categoryCode,
                'sequence_number' => $number - 1,
            ]);

            return $sku;
        });
    }

    public function segment(string $value, int $length = 3): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, $length)
            ->padRight($length, 'X');

        return (string) $normalized;
    }

    private function categoryValue(Product $product): string
    {
        if ($product->technical_category instanceof TechnicalCategory) {
            return $product->technical_category->value;
        }

        return (string) ($product->technical_category ?: 'XXX');
    }
}
