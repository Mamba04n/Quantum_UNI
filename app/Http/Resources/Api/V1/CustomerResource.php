<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Entity */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone_mobile,
            'ruc' => $this->rfc_ruc,
            'status' => $this->status,
        ];
    }
}
