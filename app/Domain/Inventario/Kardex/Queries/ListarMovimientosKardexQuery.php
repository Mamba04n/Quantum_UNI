<?php

namespace App\Domain\Inventario\Kardex\Queries;

use App\Models\StockMove;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListarMovimientosKardexQuery
{
    public function ejecutar(
        ?string $productoId,
        ?string $sku,
        ?string $tipo,
        ?string $fechaDesde,
        ?string $fechaHasta,
        int $porPagina
    ): LengthAwarePaginator {
        $baseQuery = StockMove::with(['product', 'user'])
            ->when($productoId, fn ($q) => $q->where('product_id', $productoId))
            ->when($sku, fn ($q) => $q->whereHas('product', fn ($product) => $product->where('sku', 'like', '%' . $sku . '%')))
            ->when($tipo, fn ($q) => $q->where('movement_type', $tipo))
            ->when($fechaDesde, fn ($q) => $q->whereDate('movement_date', '>=', $fechaDesde))
            ->when($fechaHasta, fn ($q) => $q->whereDate('movement_date', '<=', $fechaHasta));

        $moves = (clone $baseQuery)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->paginate($porPagina);

        $previousBalance = (clone $baseQuery)
            ->when($moves->currentPage() > 1, function ($query) use ($moves) {
                $currentPageMoves = (clone $query)
                    ->orderBy('movement_date')
                    ->orderBy('id')
                    ->forPage($moves->currentPage(), $moves->perPage())
                    ->get(['movement_date', 'id']);

                $firstMove = $currentPageMoves->first();

                if (! $firstMove) {
                    return $query->whereRaw('1 = 0');
                }

                $query->where(function ($inner) use ($firstMove) {
                    $inner->whereDate('movement_date', '<', $firstMove->movement_date)
                        ->orWhere(function ($sameDate) use ($firstMove) {
                            $sameDate->whereDate('movement_date', '=', $firstMove->movement_date)
                                ->where('id', '<', $firstMove->id);
                        });
                });
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->sum('quantity');

        $runningBalance = $previousBalance;
        $moves->getCollection()->transform(function ($move) use (&$runningBalance) {
            $runningBalance += (int) $move->quantity;
            $move->computed_balance = $runningBalance;

            return $move;
        });

        return $moves;
    }
}
