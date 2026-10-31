<?php

namespace App\Application\Http\Controllers;

use App\Application\Http\Requests\StoreClientRequest;
use App\Domain\Clientes\Services\CustomerService;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Infrastructure\Audit\AuditTrailService;
use App\Models\Entity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ClienteController
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly CustomerService $customers,
    ) {}

    public function store(StoreClientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $client = $this->customers->createFromAdmin([
            'name' => $data['name'],
            'rfc_ruc' => $data['ruc'],
            'phone_mobile' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'exact_address' => $data['address'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $this->auditTrailService->log(
            userId: $request->user()->id,
            action: 'crm.client_created',
            entityType: Entity::class,
            entityId: $client->id,
            metadata: ['ruc' => $client->rfc_ruc]
        );

        $response = ApiResponse::success(
            (new CustomerResource($client))->resolve(),
            'Client created.',
            [],
            201
        );

        if ($request->routeIs('api.legacy.clients.store')) {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', 'Sun, 31 Jan 2027 23:59:59 GMT');
            $response->headers->set('Link', '<'.route('api.v1.erp.clients.store').'>; rel="successor-version"');
        }

        return $response;
    }
}
