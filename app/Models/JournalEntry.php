<?php

namespace App\Models;

use App\Models\Entity;
use LogicException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_uuid',
        'reference',
        'currency',
        'user_id',
        'client_id',
        'client_machine_id',
        'posted_at',
        'source',
        'description',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Journal entries are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Journal entries are immutable.');
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'client_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class);
    }
}
