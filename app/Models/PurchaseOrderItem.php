<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'expected_date' => 'date',
        'received_at' => 'datetime',
    ];

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<QuotationRequestItem, $this> */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'quotation_request_item_id');
    }

    /** @return BelongsTo<SaleOrderItem, $this> */
    public function saleOrderItem(): BelongsTo
    {
        return $this->belongsTo(SaleOrderItem::class);
    }

    /** @return BelongsTo<SupplierBidSelection, $this> */
    public function supplierSelection(): BelongsTo
    {
        return $this->belongsTo(SupplierBidSelection::class, 'supplier_bid_selection_id');
    }

    /** @return BelongsTo<SupplierBidResponseItem, $this> */
    public function supplierResponseItem(): BelongsTo
    {
        return $this->belongsTo(SupplierBidResponseItem::class, 'supplier_bid_response_item_id');
    }

    /** @return BelongsTo<StockMove, $this> */
    public function kardexEntry(): BelongsTo
    {
        return $this->belongsTo(StockMove::class, 'kardex_entry_id');
    }

    /** @return HasMany<PurchaseReceiptItem, $this> */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
