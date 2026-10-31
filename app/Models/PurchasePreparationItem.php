<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePreparationItem extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_SUPPLIER_CONFIRMED = 'supplier_confirmed';

    public const STATUS_READY_FOR_PURCHASE_ORDER = 'ready_for_purchase_order';

    public const STATUS_EXCLUDED = 'excluded';

    protected $guarded = [];

    protected $casts = [
        'original_unit_cost' => 'decimal:2',
        'final_unit_cost' => 'decimal:2',
        'quantity_to_purchase' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<QuotationRequest, $this> */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /** @return BelongsTo<QuotationRequestItem, $this> */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'quotation_request_item_id');
    }

    /** @return BelongsTo<ClientOfferVersionItem, $this> */
    public function clientOfferItem(): BelongsTo
    {
        return $this->belongsTo(ClientOfferVersionItem::class, 'client_offer_item_id');
    }

    /** @return BelongsTo<SupplierBidResponseItem, $this> */
    public function originalSupplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierBidResponseItem::class, 'original_supplier_quote_id');
    }

    /** @return BelongsTo<SupplierBidResponseItem, $this> */
    public function finalSupplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierBidResponseItem::class, 'final_supplier_quote_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function originalSupplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'original_supplier_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function finalSupplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'final_supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getCostDifferenceAttribute(): float
    {
        return round((float) ($this->final_unit_cost ?? 0) - (float) ($this->original_unit_cost ?? 0), 2);
    }
}
