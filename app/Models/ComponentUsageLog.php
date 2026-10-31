<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentUsageLog extends Model
{
    protected $guarded = [];

    public function component(): BelongsTo
    {
        return $this->belongsTo(CustomerMachineComponent::class, 'component_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
