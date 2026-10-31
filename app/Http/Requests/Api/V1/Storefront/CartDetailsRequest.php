<?php

namespace App\Http\Requests\Api\V1\Storefront;

class CartDetailsRequest extends StorefrontFormRequest
{
    public function rules(): array
    {
        return [
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.id' => ['required', 'integer', 'min:1'],
            'cart.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
