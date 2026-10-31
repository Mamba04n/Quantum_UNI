<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPriceQuotation extends Model
{
    protected $guarded = [];

    protected $casts = ['quoted_at' => 'date', 'valid_until' => 'date'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierPriceItem::class);
    }
}
