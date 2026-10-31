<?php

namespace App\Models;

use App\Domain\Finanzas\ExchangeRates\Enums\ExchangeRateSourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string|null $source_reference */
class ExchangeRate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rate' => 'decimal:6',
        'rate_date' => 'date',
        'expires_at' => 'date',
        'fetched_at' => 'datetime',
        'approved_at' => 'datetime',
        'stale_approved_at' => 'datetime',
        'is_fallback' => 'boolean',
        'source_type' => ExchangeRateSourceType::class,
        'metadata' => 'array',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function staleApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stale_approved_by');
    }
}
