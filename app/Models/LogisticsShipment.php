<?php

namespace App\Models;

use App\Models\Entity;
use App\Models\ClientMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_status_item_id',
        'sale_order_item_id',
        'shipping_cost',
        'currency',
        'user_id',
        'client_id',
        'client_machine_id',
        'product_id',
        'quantity',
        'shipped_at',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'shipped_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'client_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function orderStatusItem(): BelongsTo
    {
        return $this->belongsTo(OrderStatusItem::class);
    }

    public function saleOrderItem(): BelongsTo
    {
        return $this->belongsTo(SaleOrderItem::class);
    }
}
