<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3', 'unit_price' => 'decimal:4', 'total_price' => 'decimal:2',
        'availability_checked_at' => 'datetime',
        'availability_version' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(SupplierPriceQuotation::class, 'supplier_price_quotation_id');
    }
}
