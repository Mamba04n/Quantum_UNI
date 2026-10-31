<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:200'],
            'machine' => ['nullable', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
