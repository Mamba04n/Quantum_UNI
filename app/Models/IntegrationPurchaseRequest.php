<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationPurchaseRequest extends Model
{
    protected $table = 'integration_purchase_requests';

    protected $fillable = [
        'integration_client_id',
        'code',
        'external_reference',
        'product_id',
        'item_code',
        'description',
        'quantity',
        'unit_of_measure',
        'required_date',
        'priority',
        'justification',
        'context_snapshot',
        'status',
        'purchase_order_id',
        'requested_at',
        'reviewed_at',
        'received_at',
        'idempotency_key',
        'version',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'required_date' => 'date',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'received_at' => 'datetime',
        'context_snapshot' => 'array',
        'version' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(IntegrationClient::class, 'integration_client_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
