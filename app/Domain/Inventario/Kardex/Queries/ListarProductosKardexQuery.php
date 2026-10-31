<?php

namespace App\Domain\Inventario\Kardex\Queries;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ListarProductosKardexQuery
{
    public function ejecutar(): Collection
    {
        return Product::where('active', true)
            ->orderBy('name')
            ->get();
    }
}
