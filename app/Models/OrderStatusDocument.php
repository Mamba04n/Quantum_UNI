<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusDocument extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<OrderStatus, $this> */
    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class);
    }

    /** @return BelongsTo<OrderStatusItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderStatusItem::class, 'order_status_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
