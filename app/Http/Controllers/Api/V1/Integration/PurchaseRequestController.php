<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationPurchaseRequest;
use App\Models\Product;
use App\Services\Integration\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly IdempotencyService $idempotency
    ) {}

    /**
     * Registra una requisición de compra originada por CMMS debido a faltante de stock.
     */
    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless(is_string($idempotencyKey) && filled($idempotencyKey), 422, 'El encabezado Idempotency-Key es obligatorio.');

        $input = $request->validate([
            'item_id' => 'sometimes|nullable|integer',
            'item_code' => 'required|string|max:100',
            'description' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
            'unit_of_measure' => 'sometimes|string|max:20',
            'required_date' => 'sometimes|nullable|date',
            'priority' => 'sometimes|string|in:LOW,MEDIUM,HIGH,URGENT',
            'justification' => 'sometimes|nullable|string',
            'external_reference' => 'required|string|max:100',
            'context' => 'sometimes|array',
        ]);

        $clientId = (int) $request->attributes->get('integration.client_id');
        $hash = $this->idempotency->canonicalHash($request->all());

        $cached = $this->idempotency->check($clientId, $idempotencyKey, $hash);
        if ($cached) {
            return $cached;
        }

        $ipr = DB::transaction(function () use ($input, $clientId, $idempotencyKey) {
            $productId = $input['item_id'] ?? null;
            if (! $productId && filled($input['item_code'])) {
                $product = Product::query()
                    ->where('sku', $input['item_code'])
                    ->orWhere('part_number', $input['item_code'])
                    ->first();
                $productId = $product?->id;
            }

            // Generar código correlativo ERP
            $countToday = IntegrationPurchaseRequest::whereDate('created_at', today())->count() + 1;
            $code = sprintf('IPR-%s-%04d', date('Ymd'), $countToday);

            return IntegrationPurchaseRequest::create([
                'integration_client_id' => $clientId,
                'code' => $code,
                'external_reference' => $input['external_reference'],
                'product_id' => $productId,
                'item_code' => $input['item_code'],
                'description' => $input['description'],
                'quantity' => (float) $input['quantity'],
                'unit_of_measure' => $input['unit_of_measure'] ?? 'UND',
                'required_date' => $input['required_date'] ?? null,
                'priority' => $input['priority'] ?? 'MEDIUM',
                'justification' => $input['justification'] ?? null,
                'context_snapshot' => $input['context'] ?? null,
                'status' => 'REQUESTED',
                'requested_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'version' => 1,
            ]);
        });

        $payload = [
            'data' => [
                'id' => (string) $ipr->id,
                'code' => $ipr->code,
                'external_reference' => $ipr->external_reference,
                'item' => [
                    'id' => $ipr->product_id ? (string) $ipr->product_id : null,
                    'code' => $ipr->item_code,
                    'description' => $ipr->description,
                ],
                'quantity' => (float) $ipr->quantity,
                'unit_of_measure' => $ipr->unit_of_measure,
                'priority' => $ipr->priority,
                'status' => $ipr->status,
                'required_date' => $ipr->required_date?->format('Y-m-d'),
                'created_at' => $ipr->created_at->toIso8601ZuluString(),
                'version' => $ipr->version,
            ],
        ];

        $this->idempotency->record(
            $clientId,
            $idempotencyKey,
            $hash,
            'purchase_requests.create',
            201,
            $payload,
            'integration_purchase_request',
            $ipr->id
        );

        return response()->json($payload, 201);
    }

    /**
     * Consulta el estado y detalle de una requisición de compra.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $clientId = (int) $request->attributes->get('integration.client_id');

        $ipr = IntegrationPurchaseRequest::query()
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', $id);
                } else {
                    $q->where('code', $id)->orWhere('external_reference', $id);
                }
            })
            ->first();

        abort_unless($ipr, 404, 'La solicitud de compra no existe.');
        abort_unless((int) $ipr->integration_client_id === $clientId, 404, 'Solicitud no pertenece a este consumidor.');

        return response()->json([
            'data' => [
                'id' => (string) $ipr->id,
                'code' => $ipr->code,
                'external_reference' => $ipr->external_reference,
                'item' => [
                    'id' => $ipr->product_id ? (string) $ipr->product_id : null,
                    'code' => $ipr->item_code,
                    'description' => $ipr->description,
                ],
                'quantity' => (float) $ipr->quantity,
                'unit_of_measure' => $ipr->unit_of_measure,
                'priority' => $ipr->priority,
                'status' => $ipr->status,
                'purchase_order_id' => $ipr->purchase_order_id ? (string) $ipr->purchase_order_id : null,
                'required_date' => $ipr->required_date?->format('Y-m-d'),
                'requested_at' => $ipr->requested_at?->toIso8601ZuluString(),
                'reviewed_at' => $ipr->reviewed_at?->toIso8601ZuluString(),
                'received_at' => $ipr->received_at?->toIso8601ZuluString(),
                'version' => $ipr->version,
            ],
        ]);
    }
}
