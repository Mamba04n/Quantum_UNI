<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerMachineComponent extends Model
{
    public const STATUS_GOOD = 'bueno';

    public const STATUS_ATTENTION = 'atencion_proxima';

    public const STATUS_CRITICAL = 'critico';

    public const STATUS_EXPIRED = 'vencido';

    public const STATUS_REPLACED = 'reemplazado';

    public const STATUS_OUT = 'fuera_de_servicio';

    protected $guarded = [];

    protected $casts = [
        'last_installation_date' => 'date',
        'last_updated_at' => 'datetime',
        'consumed_percentage' => 'decimal:2',
        'quantity' => 'integer',
        'last_replacement_hourmeter' => 'integer',
        'current_usage' => 'integer',
        'estimated_life' => 'integer',
        'residual_life' => 'integer',
    ];

    /** @return BelongsTo<ClientMachine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class, 'client_machine_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ComponentUsageLog::class, 'component_id')->latest();
    }

    public function getPriorityAttribute(): int
    {
        return match ($this->status) {
            self::STATUS_EXPIRED => 1,
            self::STATUS_CRITICAL => 2,
            self::STATUS_ATTENTION => 3,
            default => 4,
        };
    }
}
