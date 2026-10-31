<?php

namespace App\Services\Storefront;

use App\Enums\TechnicalCategory;
use App\Models\Product;
use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ProductService
{
    public function featured(int $limit = 8): Collection
    {
        return Product::query()
            ->where('active', true)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function search(string $search, int $limit = 8): Collection
    {
        $search = trim($search);
        if (mb_strlen(trim($search)) < 2) {
            return new Collection;
        }

        return Product::query()
            ->where('active', true)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('part_number', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    private function catalogQuery(string $search = '', string $type = '', string $brand = '', string $category = '', bool $inStock = false): \Illuminate\Database\Eloquent\Builder
    {
        $search = trim($search);
        return Product::query()
            ->where('active', true)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('part_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($brand !== '', fn ($query) => $query->whereRaw('UPPER(TRIM(brand)) = ?', [mb_strtoupper(trim($brand))]))
            ->when($category !== '', fn ($query) => $query->where('technical_category', $category))
            ->when($inStock, fn ($query) => $query->where('stock_on_hand', '>', 0));
    }

    public function paginate(string $search = '', string $type = '', string $brand = '', string $category = '', int $perPage = 12, bool $inStock = false, string $sort = 'name'): LengthAwarePaginator
    {
        return $this->catalogQuery($search, $type, $brand, $category, $inStock)
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('id'), fn ($query) => $query->orderBy('name', $sort === 'name_desc' ? 'desc' : 'asc'))
            ->orderBy('id')
            ->paginate(min(48, max(1, $perPage)));
    }

    public function filterOptions(string $search = '', string $type = '', string $brand = '', string $category = '', bool $inStock = false): array
    {
        $brands = $this->brandCounts($search, $type, $category, $inStock);
        return [
            'types' => Product::query()->where('active', true)->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'brands' => $brands->keys(),
            'brandCounts' => $brands,
            'categories' => $this->categoryOptions(null, $search, $type, $brand, $inStock),
        ];
    }

    public function brandCounts(string $search = '', string $type = '', string $category = '', bool $inStock = false): SupportCollection
    {
        $counts = $this->catalogQuery($search, $type, '', $category, $inStock)->whereNotNull('brand')
            ->selectRaw('UPPER(TRIM(brand)) AS normalized_brand, COUNT(*) AS aggregate')
            ->groupByRaw('UPPER(TRIM(brand))')->pluck('aggregate', 'normalized_brand');

        return Brand::query()->where('is_active', true)->orderBy('name')->pluck('name')
            ->map(fn ($name) => trim($name))
            ->filter(fn ($name) => $name !== '' && $name !== '-')
            ->unique(fn ($name) => mb_strtoupper($name))
            ->filter(fn ($name) => (int) $counts->get(mb_strtoupper($name), 0) > 0)
            ->mapWithKeys(fn ($name) => [$name => (int) $counts->get(mb_strtoupper($name))]);
    }

    public function categoryOptions(?int $limit = null, string $search = '', string $type = '', string $brand = '', bool $inStock = false): SupportCollection
    {
        $categories = $this->catalogQuery($search, $type, $brand, '', $inStock)
            ->whereNotNull('technical_category')
            ->where('technical_category', '!=', '')
            ->selectRaw('technical_category, count(*) as products_count')
            ->groupBy('technical_category')
            ->orderByDesc('products_count')
            ->orderBy('technical_category')
            ->get()
            ->map(function ($row) {
                $value = (string) $row->getRawOriginal('technical_category');

                return [
                    'value' => $value,
                    'label' => $this->categoryLabel($value),
                    'code' => $this->categoryCode($value),
                    'products_count' => (int) $row->products_count,
                ];
            });

        return $limit ? $categories->take($limit)->values() : $categories->values();
    }

    public function related(Product $product, int $limit = 4): Collection
    {
        return Product::query()
            ->where('active', true)
            ->whereKeyNot($product->getKey())
            ->where(fn ($query) => $query->where('type', $product->getRawOriginal('type'))->orWhere('brand', $product->brand))
            ->limit($limit)
            ->get();
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            TechnicalCategory::Electrical->value => 'Eléctrico',
            TechnicalCategory::Mechanical->value => 'Mecánico',
            TechnicalCategory::Hydraulic->value => 'Hidráulico',
            TechnicalCategory::Pneumatic->value => 'Neumático',
            TechnicalCategory::Filtration->value => 'Filtración',
            TechnicalCategory::Lubrication->value => 'Lubricación',
            TechnicalCategory::Engine->value => 'Motor',
            TechnicalCategory::Transmission->value => 'Transmisión',
            TechnicalCategory::Other->value => 'Otro',
            default => ucfirst(str_replace(['_', '-'], ' ', $category)),
        };
    }

    private function categoryCode(string $category): string
    {
        $label = $this->categoryLabel($category);
        $words = preg_split('/\s+/', trim($label)) ?: [];
        $code = count($words) > 1
            ? implode('', array_map(fn ($word) => mb_substr($word, 0, 1), $words))
            : mb_substr($label, 0, 2);

        return mb_strtoupper($code ?: 'QS');
    }
}
