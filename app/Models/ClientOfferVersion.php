<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientOfferVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'date',
        'exchange_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'snapshot_json' => 'array',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /** @return HasMany<ClientOfferVersionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ClientOfferVersionItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
