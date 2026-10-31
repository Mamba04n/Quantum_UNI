<?php

namespace App\Http\Resources\Api\V1\Finance;

use App\Domain\Finanzas\Api\Support\DecimalString;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->payment_code,
            'type' => $this->payment_type,
            'account' => [
                'type' => $this->payable_type,
                'id' => $this->payable_id,
                'receivable_id' => $this->accounts_receivable_id,
                'payable_id' => $this->accounts_payable_id,
                'invoice_id' => $this->invoice_id,
            ],
            'currency' => $this->currency,
            'amounts' => [
                'amount' => DecimalString::normalize($this->amount),
                'original' => DecimalString::normalize($this->amount_original),
                'exchange_rate' => DecimalString::normalize($this->exchange_rate, 6),
            ],
            'payment_date' => $this->payment_date?->toDateString(),
            'method' => $this->payment_method,
            'reference' => $this->payment_reference ?: $this->receipt_reference,
            'notes' => $this->notes,
            'responsible_user_id' => $this->registered_by,
            'has_document' => filled($this->payment_evidence_path),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
