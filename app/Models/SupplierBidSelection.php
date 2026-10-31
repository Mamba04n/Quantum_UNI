<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBidSelection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'selected_quantity' => 'decimal:2',
        'selected_unit_cost' => 'decimal:2',
        'selected_unit_price' => 'decimal:2',
        'selected_unit_price_nio' => 'decimal:2',
        'estimated_arrival_date' => 'date',
        'is_alternative' => 'boolean',
        'selected_at' => 'datetime',
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

    /** @return BelongsTo<SupplierBidResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(SupplierBidResponse::class, 'supplier_bid_response_id');
    }

    /** @return BelongsTo<SupplierBidResponseItem, $this> */
    public function responseItem(): BelongsTo
    {
        return $this->belongsTo(SupplierBidResponseItem::class, 'supplier_bid_response_item_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'selected_supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    public function getSubtotalAttribute(): float
    {
        return round((float) $this->selected_quantity * (float) $this->selected_unit_cost, 2);
    }
}
