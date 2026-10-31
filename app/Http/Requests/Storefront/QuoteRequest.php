<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'ruc' => ['nullable', 'string', 'max:50'],
            'machinery' => ['nullable', 'string', 'max:150'],
            'machine_brand' => ['nullable', 'string', 'max:100'],
            'machine_model' => ['nullable', 'string', 'max:100'],
            'equipment_serial' => ['nullable', 'string', 'max:100'],
            'urgency' => ['nullable', 'string', 'in:baja,media,alta,urgente'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }
}
