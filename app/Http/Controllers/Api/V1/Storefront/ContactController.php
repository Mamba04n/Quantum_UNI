<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Requests\Api\V1\Storefront\ContactRequest;
use App\Services\Storefront\ContactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactController
{
    public function store(ContactRequest $request, ContactService $contacts): JsonResponse
    {
        $data = $request->validated();

        $id = $contacts->create([
            'name' => $data['nombre'],
            'email' => $data['email'],
            'phone' => $data['telefono'] ?? null,
            'message' => $data['mensaje'],
        ]);

        return ApiResponse::success([
            'id' => $id,
            'status' => 'received',
        ], 'Contact request received.', ['notification' => 'sent'], 201);
    }
}
