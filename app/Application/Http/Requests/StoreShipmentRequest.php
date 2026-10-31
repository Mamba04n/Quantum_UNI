<?php

namespace App\Application\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operations.shipments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:50'],
            'shipping_cost' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'client_id' => ['nullable', 'integer', 'exists:entities,id'],
            'client_machine_id' => ['nullable', 'integer', 'exists:client_machines,id'],
            'timestamp' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_id' => strip_tags((string) $this->input('order_id')),
            'currency' => strtoupper(strip_tags((string) $this->input('currency'))),
            'timestamp' => $this->input('timestamp') ?? now()->toDateTimeString(),
        ]);
    }
}
