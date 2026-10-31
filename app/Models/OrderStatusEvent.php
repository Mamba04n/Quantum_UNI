<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'customer_notification_queued_at' => 'datetime',
        'customer_notification_sent_at' => 'datetime',
        'customer_notification_failed_at' => 'datetime',
    ];

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderStatusItem::class, 'order_status_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
