<?php

namespace App\Http\Resources\Api\V1\Finance;

use App\Domain\Finanzas\Api\Support\DecimalString;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->invoice_number,
            'type' => $this->invoice_type,
            'entity' => $this->whenLoaded('entity', fn () => [
                'id' => $this->entity?->id,
                'name' => $this->entity?->name,
            ]),
            'description' => $this->description,
            'currency' => $this->currency,
            'amounts' => [
                'subtotal' => DecimalString::normalize($this->subtotal),
                'tax' => DecimalString::normalize($this->tax_amount),
                'discount' => DecimalString::normalize($this->discount_amount),
                'total' => DecimalString::normalize($this->total),
                'legacy_amount_nio' => DecimalString::normalize($this->amount_nio),
                'usd' => DecimalString::normalize($this->amount_usd),
                'retention' => DecimalString::normalize($this->retention_amount),
            ],
            'date' => $this->date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'responsible_user_id' => $this->generated_by ?: $this->uploaded_by,
            'has_document' => filled($this->invoice_file_path),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
