<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBidItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
        'is_quoted' => 'boolean',
        'estimated_arrival_date' => 'date',
    ];

    /** @return BelongsTo<SupplierBid, $this> */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(SupplierBid::class, 'supplier_bid_id');
    }

    /** @return BelongsTo<QuotationRequestItem, $this> */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'quotation_request_item_id');
    }
}
