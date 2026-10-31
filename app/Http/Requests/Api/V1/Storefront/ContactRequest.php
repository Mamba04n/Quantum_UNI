<?php

namespace App\Http\Requests\Api\V1\Storefront;

class ContactRequest extends StorefrontFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->input('nombre', $this->input('name')),
            'telefono' => $this->input('telefono', $this->input('phone')),
            'mensaje' => $this->input('mensaje', $this->input('message')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'not_regex:/[\r\n]/', 'email', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'mensaje' => ['required', 'string', 'max:5000'],
        ];
    }
}
