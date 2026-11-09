<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Integration\ItemResource;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ItemController extends Controller
{
    /**
     * Catálogo paginado por cursor opaco con soporte de búsqueda y sync incremental.
     */
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:200',
            'search' => 'sometimes|string|max:100',
            'updated_since' => 'sometimes|date_format:Y-m-d\TH:i:s.u\Z',
            'cursor' => 'sometimes|string|max:8192',
        ]);

        $clientId = $request->attributes->get('integration.client_id');

        if (isset($input['cursor'])) {
            try {
                $state = json_decode(Crypt::decryptString($input['cursor']), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException) {
                abort(422, 'Cursor de paginación inválido.');
            }

            abort_unless(($state['client'] ?? null) === $clientId, 422, 'Cursor no pertenece a este consumidor.');
            $limit = (int) ($state['limit'] ?? 100);
            $snapshotUntil = $state['snapshot_until'];
            $lastId = (int) ($state['last_id'] ?? 0);
            $since = $state['since'] ?? null;
            $search = $state['search'] ?? null;
        } else {
            $limit = (int) ($input['limit'] ?? 100);
            $snapshotUntil = now()->toIso8601ZuluString();
            $lastId = 0;
            $since = $input['updated_since'] ?? null;
            $search = isset($input['search']) ? trim($input['search']) : null;
        }

        $query = Product::query();

        // Limitar a la instantánea temporal
        $untilCarbon = CarbonImmutable::parse($snapshotUntil);
        $query->where('updated_at', '<=', $untilCarbon->format('Y-m-d H:i:s'));

        // Filtro incremental por updated_since
        if ($since) {
            $sinceCarbon = CarbonImmutable::parse($since);
            $query->where('updated_at', '>=', $sinceCarbon->format('Y-m-d H:i:s'));
        }

        // Búsqueda segura por sku, part_number o nombre
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('part_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $totalCount = (clone $query)->count();

        // Paginación estable basada en id monotónico
        $query->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit($limit + 1);

        $items = $query->get();
        $hasMore = $items->count() > $limit;
        $pageItems = $items->take($limit);

        $nextCursor = null;
        if ($hasMore && $pageItems->isNotEmpty()) {
            $nextState = [
                'client' => $clientId,
                'limit' => $limit,
                'snapshot_until' => $snapshotUntil,
                'last_id' => $pageItems->last()->id,
                'since' => $since,
                'search' => $search,
            ];
            $nextCursor = Crypt::encryptString(json_encode($nextState, JSON_THROW_ON_ERROR));
        }

        return response()->json([
            'data' => ItemResource::collection($pageItems),
            'meta' => [
                'next_cursor' => $nextCursor,
                'snapshot_until' => $snapshotUntil,
                'limit' => $limit,
                'count' => $pageItems->count(),
                'total' => $totalCount,
            ],
        ]);
    }

    /**
     * Consulta de detalle de un producto individual con whitelist DTO.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::query()->find($id);
        abort_unless($product, 404, 'El ítem solicitado no existe en el catálogo ERP.');

        return response()->json([
            'data' => new ItemResource($product),
        ]);
    }
}
