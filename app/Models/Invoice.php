<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $generated_by
 * @property int|null $uploaded_by
 * @property string|null $invoice_file_path
 * @property numeric-string|float|int $total
 * @property string|null $invoice_type
 * @property string|null $currency
 * @property numeric-string|float|int $subtotal
 * @property numeric-string|float|int $tax_amount
 * @property numeric-string|float|int $discount_amount
 */
class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
    ];

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sales_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getNetAmountAttribute(): float
    {
        return $this->amount_nio - $this->retention_amount;
    }
}
