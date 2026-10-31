<?php

namespace App\Http\Resources\Api\V1\Finance;

use App\Domain\Finanzas\Api\Support\DecimalString;
use App\Models\AccountPayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountPayable */
class AccountPayableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->payable_code,
            'invoice_number' => $this->invoice_number,
            'entity' => $this->whenLoaded('entity', fn () => [
                'id' => $this->entity?->id,
                'name' => $this->entity?->name,
            ]),
            'concept' => $this->concept,
            'currency' => $this->currency,
            'amounts' => [
                'total' => DecimalString::normalize($this->total_amount),
                'paid' => DecimalString::normalize($this->paid_amount),
                'balance' => DecimalString::subtract($this->total_amount, $this->paid_amount),
                'usd' => DecimalString::normalize($this->amount_usd),
                'exchange_rate' => DecimalString::normalize($this->exchange_rate, 6),
            ],
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'responsible_user_id' => $this->created_by,
            'has_document' => filled($this->support_document_path),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
