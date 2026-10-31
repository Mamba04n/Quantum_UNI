<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientApproval extends Model
{
    public const STATUS_APPROVED_TOTAL = 'aprobada_cliente_total';
    public const STATUS_APPROVED_PARTIAL = 'aprobada_cliente_parcial';
    public const STATUS_REJECTED = 'rechazada_cliente';
    public const STATUS_CHANGES_REQUESTED = 'requiere_ajuste_cliente';

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    public function clientOffer(): BelongsTo
    {
        return $this->belongsTo(ClientOfferVersion::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
