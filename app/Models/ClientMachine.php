<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $client_id
 * @property Carbon|null $purchase_date
 */
class ClientMachine extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'machine_type',
        'name',
        'brand',
        'model',
        'serial_number',
        'internal_code',
        'engine_serial',
        'year',
        'hourmeter',
        'hourmeter_date',
        'status',
        'image_path',
        'notes',
        'technical_details',
    ];

    protected $casts = [
        'hourmeter' => 'integer',
        'hourmeter_date' => 'date',
    ];

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(CustomerMachineComponent::class, 'client_machine_id');
    }
}
