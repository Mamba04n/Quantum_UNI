<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleOrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    /** @return BelongsTo<SaleOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function clientOfferItem()
    {
        return $this->belongsTo(ClientOfferVersionItem::class, 'client_offer_item_id');
    }

    /** @return BelongsTo<QuotationRequestItem, $this> */
    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'quotation_request_item_id');
    }

    public function kardexEntry()
    {
        return $this->belongsTo(StockMove::class, 'kardex_entry_id');
    }

    public function kardexOutput()
    {
        return $this->belongsTo(StockMove::class, 'kardex_output_id');
    }

    /** @return HasOne<OrderStatusItem, $this> */
    public function orderStatusItem(): HasOne
    {
        return $this->hasOne(OrderStatusItem::class);
    }
}
