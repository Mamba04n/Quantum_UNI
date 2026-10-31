<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierBidResponse extends Model
{
    protected $guarded = [];

    protected $casts = [
        'preliquidation' => 'array',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<QuotationRequest, $this> */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /** @return BelongsTo<SupplierBid, $this> */
    public function supplierBid(): BelongsTo
    {
        return $this->belongsTo(SupplierBid::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /** @return HasMany<SupplierBidResponseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierBidResponseItem::class);
    }

    /** @return HasOne<OrderTrackerAttachment, $this> */
    public function supplierDocumentAttachment(): HasOne
    {
        return $this->hasOne(OrderTrackerAttachment::class, 'source_id')
            ->whereIn('source_module', ['supplier_bid_responses', 'supplier_bid_response'])
            ->latestOfMany();
    }

    /** @return HasMany<OrderTrackerAttachment, $this> */
    public function supplierDocumentAttachments(): HasMany
    {
        return $this->hasMany(OrderTrackerAttachment::class, 'source_id')
            ->whereIn('source_module', ['supplier_bid_responses', 'supplier_bid_response']);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getQuotedTotalAttribute(): float
    {
        return (float) $this->items
            ->whereIn('item_status', ['quoted', 'alternative'])
            ->sum(fn (SupplierBidResponseItem $item) => (float) $item->quoted_quantity * (float) $item->unit_cost);
    }

    public function getTransportTypeAttribute(): string
    {
        return $this->preliquidation['settings']['transport'] ?? 'air';
    }

    public function getTransportTypeLabelAttribute(): string
    {
        return $this->transport_type === 'sea' ? 'Marítimo' : 'Aéreo';
    }

    public function getTransportTypeBadgeAttribute(): string
    {
        return $this->transport_type === 'sea' ? '🚢 Marítimo' : '✈️ Aéreo';
    }
}
