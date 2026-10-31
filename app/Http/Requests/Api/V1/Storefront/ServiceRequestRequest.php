<?php

namespace App\Http\Requests\Api\V1\Storefront;

class ServiceRequestRequest extends StorefrontFormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'not_regex:/[\r\n]/', 'email', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'maquinaria' => ['nullable', 'string', 'max:200'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ];
    }
}
