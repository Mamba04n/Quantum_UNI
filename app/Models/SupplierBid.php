<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBid extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'date',
        'is_winner' => 'boolean',
        'selected_at' => 'datetime',
        'exchange_rate' => 'decimal:4',
    ];

    /** @return BelongsTo<QuotationRequest, $this> */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /** @return HasMany<SupplierBidItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierBidItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum('line_total');
    }
}
