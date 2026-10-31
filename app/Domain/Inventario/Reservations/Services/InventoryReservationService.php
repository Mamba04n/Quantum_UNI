<?php

namespace App\Domain\Inventario\Reservations\Services;

use App\Models\ActivityLog;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\OrderTrackerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    public function __construct(private readonly OrderTrackerService $trackerService) {}

    public function reserveForQuotation(QuotationRequest $request, User $user): void
    {
        if (! $user->can('direct_order.reserve_stock') && ! $user->can('direct_order.validate_stock')) {
            throw ValidationException::withMessages(['stock' => 'No tienes permiso para reservar inventario.']);
        }

        $request->loadMissing('items.product');

        DB::transaction(function () use ($request, $user) {
            foreach ($request->items as $item) {
                $product = $item->product_id
                    ? Product::whereKey($item->product_id)->lockForUpdate()->first()
                    : null;

                if (! $product && filled($item->part_number)) {
                    $product = Product::query()
                        ->where('sku', $item->part_number)
                        ->orWhere('part_number', $item->part_number)
                        ->lockForUpdate()
                        ->first();
                }

                if (! $product) {
                    throw ValidationException::withMessages(['stock' => "La partida {$this->lineLabel($item)} no esta vinculada a un producto de inventario."]);
                }

                if (! $product || (float) $product->stock_on_hand < (float) $item->quantity) {
                    throw ValidationException::withMessages(['stock' => "No hay stock suficiente para {$this->lineLabel($item)}."]);
                }

                $reserved = InventoryReservation::where('product_id', $product->id)
                    ->where('status', 'reserved')
                    ->where(function ($query) use ($request) {
                        $query->whereNull('quotation_request_id')->orWhere('quotation_request_id', '!=', $request->id);
                    })
                    ->sum('quantity');

                if (((float) $product->stock_on_hand - (float) $reserved) < (float) $item->quantity) {
                    throw ValidationException::withMessages(['stock' => "El stock disponible de {$this->lineLabel($item)} ya esta reservado para otra solicitud."]);
                }

                if (! $item->product_id) {
                    $item->forceFill(['product_id' => $product->id])->save();
                }

                InventoryReservation::updateOrCreate(
                    [
                        'quotation_request_id' => $request->id,
                        'quotation_request_item_id' => $item->id,
                        'product_id' => $product->id,
                        'status' => 'reserved',
                    ],
                    [
                        'quantity' => $item->quantity,
                        'reserved_by' => $user->id,
                        'reserved_at' => now(),
                        'notes' => 'Reserva creada desde flujo comercial.',
                    ]
                );
            }

            $tracker = $this->trackerService->trackerForQuotation($request);
            $this->trackerService->recordActivity(
                $tracker,
                'inventory.reserved',
                "Inventario reservado para solicitud {$request->reference}",
                'direct_order',
                'quotation_requests',
                $request->id,
                null,
                'reserved',
                $user
            );

            ActivityLog::log('inventario', 'inventory.reserved', "Inventario reservado para {$request->reference}", [
                'quote_id' => $request->id,
            ]);
        });
    }

    public function releaseForQuotation(QuotationRequest $request, User $user, string $reason = 'Liberado desde flujo comercial'): void
    {
        DB::transaction(function () use ($request, $user, $reason) {
            InventoryReservation::where('quotation_request_id', $request->id)
                ->where('status', 'reserved')
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'notes' => $reason,
                ]);

            $tracker = $this->trackerService->trackerForQuotation($request);
            $this->trackerService->recordActivity(
                $tracker,
                'inventory.reservation_released',
                $reason,
                'direct_order',
                'quotation_requests',
                $request->id,
                'reserved',
                'released',
                $user
            );
        });
    }

    private function lineLabel($item): string
    {
        return $item->part_number ?: ($item->part_name ?: $item->description);
    }
}
