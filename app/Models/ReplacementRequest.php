<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'supplier_unit_cost' => 'decimal:2',
        'client_unit_price' => 'decimal:2',
        'document_paths' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderStatusItem::class, 'order_status_item_id');
    }

    public function originalSupplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'original_supplier_id');
    }

    public function newSupplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'new_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
