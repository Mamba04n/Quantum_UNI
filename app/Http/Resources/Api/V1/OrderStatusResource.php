<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderStatus */
class OrderStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_order_id' => $this->sale_order_id,
            'order_code' => $this->order_code,
            'tracking_code' => $this->tracker?->tracking_code,
            'administrative_phase' => $this->administrative_phase,
            'administrative_status' => $this->administrative_status,
            'progress_percentage' => (int) ($this->progress_percentage ?? 0),
            'currency' => $this->currency,
            'total_amount' => $this->total_amount,
            'estimated_arrival_date' => optional($this->estimated_arrival_date)->toDateString(),
        ];
    }
}
