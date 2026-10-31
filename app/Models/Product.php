<?php

namespace App\Models;

use App\Enums\PieceRotationType;
use App\Enums\ProductType;
use App\Enums\TechnicalCategory;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $stock_on_hand
 * @property string|null $image_url
 * @property int $cart_quantity
 * @property float $cart_line_total
 * @property int $products_count
 * @property string|null $model
 */
class Product extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'products';

    protected $guarded = [];

    protected $casts = [
        'type' => ProductType::class,
        'piece_rotation_type' => PieceRotationType::class,
        'technical_category' => TechnicalCategory::class,
        'sales_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'stock_on_hand' => 'integer',
        'active' => 'boolean',
    ];

    public function stockMoves(): HasMany
    {
        return $this->hasMany(StockMove::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'related_product_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
