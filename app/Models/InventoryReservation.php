<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'consumed_quantity' => 'decimal:2',
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
        'external_context' => 'array',
        'version' => 'integer',
    ];

    public function integrationClient(): BelongsTo
    {
        return $this->belongsTo(IntegrationClient::class, 'integration_client_id');
    }

    public function stockMove(): BelongsTo
    {
        return $this->belongsTo(StockMove::class, 'stock_move_id');
    }

    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationRequestItem::class, 'quotation_request_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reserver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }
}
