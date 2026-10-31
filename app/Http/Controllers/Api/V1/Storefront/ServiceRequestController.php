<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Requests\Api\V1\Storefront\ServiceRequestRequest;
use App\Services\Storefront\ServiceRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceRequestController
{
    public function store(ServiceRequestRequest $request, ServiceRequestService $requests): JsonResponse
    {
        $data = $request->validated();
        $id = $requests->create([
            'name' => $data['nombre'],
            'email' => $data['email'],
            'phone' => $data['telefono'] ?? null,
            'location' => $data['ubicacion'] ?? null,
            'machine' => $data['maquinaria'] ?? null,
            'description' => $data['descripcion'],
        ], $request->file('imagen'));

        return ApiResponse::success([
            'id' => $id,
            'status' => 'received',
        ], 'Service request received.', ['notification' => 'legacy_email_not_migrated'], 201);
    }
}
