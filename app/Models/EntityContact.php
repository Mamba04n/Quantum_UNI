<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityContact extends Model
{
    protected $fillable = [
        'entity_id', 'name', 'position', 'department', 'email', 'mobile',
        'office_phone', 'whatsapp', 'is_primary', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
