<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class IdentityController extends Controller
{
    public function capabilities(): JsonResponse
    {
        return response()->json(['data' => [
            'application' => 'QuantumComplete',
            'api_version' => '1.1',
            'resources' => ['capabilities', 'users', 'customers', 'assets', 'employees'],
            'authentication' => 'bearer',
        ]]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::query()->find($id);
        abort_unless($user, 404);
        abort_if($user->hasRole(User::ROLE_CLIENTE_TIENDA), 404);

        return response()->json(['data' => [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'active' => (bool) $user->is_active,
            'updated_at' => $user->updated_at?->toIso8601ZuluString(),
        ]]);
    }
}
