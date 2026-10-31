<?php

namespace App\Http\Resources\Api\V1\Finance;

use App\Domain\Finanzas\Api\Support\DecimalString;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExchangeRate */
class ExchangeRateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'base_currency' => $this->base_currency,
            'quote_currency' => $this->quote_currency,
            'rate' => DecimalString::normalize($this->rate, 6),
            'rate_date' => $this->rate_date?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'source' => [
                'type' => $this->source_type?->value,
                'name' => $this->source,
                'reference' => $this->source_reference,
                'is_fallback' => (bool) $this->is_fallback,
            ],
            'fetched_at' => $this->fetched_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
