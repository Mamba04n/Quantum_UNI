<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationIdempotencyKey extends Model
{
    protected $table = 'integration_idempotency_keys';

    protected $fillable = [
        'integration_client_id',
        'idempotency_key',
        'request_hash',
        'operation',
        'response_status',
        'response_payload',
        'resource_type',
        'resource_id',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'response_payload' => 'array',
        'resource_id' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(IntegrationClient::class, 'integration_client_id');
    }
}
