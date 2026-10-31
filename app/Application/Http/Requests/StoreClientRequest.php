<?php

namespace App\Application\Http\Requests;

use App\Rules\NicaraguanRucRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.clients.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ruc' => [
                'required',
                'string',
                'max:20',
                Rule::unique('entities', 'rfc_ruc')->where('type', 'cliente'),
                new NicaraguanRucRule,
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'not_regex:/[\r\n]/', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags((string) $this->input('name')),
            'ruc' => strtoupper(strip_tags((string) $this->input('ruc'))),
            'phone' => strip_tags((string) $this->input('phone')),
            'email' => strip_tags((string) $this->input('email')),
            'address' => strip_tags((string) $this->input('address')),
        ]);
    }
}
