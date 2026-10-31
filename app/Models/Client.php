<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Capa temporal de compatibilidad. El modelo canónico es Entity.
 *
 * @deprecated Use Entity::query()->where('type', 'cliente').
 */
class Client extends Entity
{
    protected $table = 'entities';

    protected $fillable = [
        'name', 'ruc', 'phone', 'email', 'address', 'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('customer', fn (Builder $query) => $query->where('type', 'cliente'));
        static::creating(function (Client $client): void {
            $client->type = 'cliente';
        });
    }

    public function getRucAttribute(): ?string
    {
        return $this->rfc_ruc;
    }

    public function setRucAttribute(?string $value): void
    {
        $this->rfc_ruc = $value;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->phone_mobile;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->phone_mobile = $value;
    }
}
