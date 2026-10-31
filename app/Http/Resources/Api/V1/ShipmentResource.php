<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LogisticsShipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LogisticsShipment */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'client_id' => $this->client_id,
            'client_machine_id' => $this->client_machine_id,
            'shipping_cost' => $this->shipping_cost,
            'currency' => $this->currency,
            'shipped_at' => optional($this->shipped_at)->toISOString(),
        ];
    }
}
