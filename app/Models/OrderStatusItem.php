<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $quantity_ordered
 * @property string $quantity_shipped
 * @property string $quantity_in_customs
 * @property string $quantity_kardex_entry
 * @property string $quantity_kardex_output
 * @property string $quantity_dispatched
 * @property string $quantity_cancelled
 */
class OrderStatusItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity_ordered' => 'decimal:2',
        'quantity_confirmed' => 'decimal:2',
        'quantity_shipped' => 'decimal:2',
        'quantity_in_customs' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'quantity_kardex_entry' => 'decimal:2',
        'quantity_kardex_output' => 'decimal:2',
        'quantity_dispatched' => 'decimal:2',
        'quantity_delivered' => 'decimal:2',
        'quantity_cancelled' => 'decimal:2',
        'quantity_pending' => 'decimal:2',
        'estimated_supplier_dispatch_date' => 'date',
        'actual_supplier_dispatch_date' => 'date',
        'estimated_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
        'customs_entry_date' => 'date',
        'customs_release_date' => 'date',
        'warehouse_received_date' => 'date',
        'kardex_entry_date' => 'date',
        'kardex_output_date' => 'date',
        'delivery_dispatch_date' => 'date',
        'delivered_at' => 'datetime',
        'replacement_required' => 'boolean',
        'waiting_for_item_ids' => 'array',
    ];

    /** @return BelongsTo<OrderStatus, $this> */
    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class);
    }

    /** @return BelongsTo<SaleOrderItem, $this> */
    public function saleOrderItem(): BelongsTo
    {
        return $this->belongsTo(SaleOrderItem::class);
    }

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrderStatusDocument::class);
    }

    public function shipmentBatchItems(): HasMany
    {
        return $this->hasMany(ShipmentBatchItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->latest();
    }

    public function replacementRequests(): HasMany
    {
        return $this->hasMany(ReplacementRequest::class);
    }
}
