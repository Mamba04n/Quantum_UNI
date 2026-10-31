<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierBidResponseItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quoted_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'preliquidated_unit_price' => 'decimal:2',
        'preliquidated_unit_price_nio' => 'decimal:2',
        'ddp_unit_cost' => 'decimal:2',
        'cif_unit_cost' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'estimated_arrival_date' => 'date',
        'is_alternative' => 'boolean',
    ];

    /** @return BelongsTo<SupplierBidResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(SupplierBidResponse::class, 'supplier_bid_response_id');
    }

    /** @return BelongsTo<QuotationRequestItem, $this> */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'quotation_request_item_id');
    }

    /** @return BelongsTo<QuotationRequestItem, $this> */
    public function customerRequestItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'customer_request_item_id');
    }

    /** @return HasOne<SupplierBidSelection, $this> */
    public function selection(): HasOne
    {
        return $this->hasOne(SupplierBidSelection::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<OrderTrackerAttachment, $this> */
    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderTrackerAttachment::class, 'source_id')
            ->whereIn('source_module', ['supplier_bid_response_items', 'supplier_bid_response_item']);
    }

    /** @return HasOne<OrderTrackerAttachment, $this> */
    public function attachment(): HasOne
    {
        return $this->hasOne(OrderTrackerAttachment::class, 'source_id')
            ->whereIn('source_module', ['supplier_bid_response_items', 'supplier_bid_response_item'])
            ->latestOfMany();
    }

    public function getIsSelectableAttribute(): bool
    {
        return in_array($this->item_status, ['quoted', 'alternative'], true)
            && (float) ($this->unit_cost ?? 0) > 0
            && (float) ($this->quoted_quantity ?? 0) > 0;
    }

    public function getSubtotalAttribute(): float
    {
        return round((float) $this->quoted_quantity * (float) $this->unit_cost, 2);
    }
}
