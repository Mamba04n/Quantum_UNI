<?php

namespace App\Domain\Proveedores\Perfil\Queries;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Builder;

class ListarProveedoresQuery
{
    public function ejecutar(array $filtros = []): Builder
    {
        return Entity::query()
            ->where('type', 'proveedor')
            ->when($filtros['search'] ?? null, function (Builder $q, string $search) {
                $search = trim($search);

                $q->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('phone_mobile', 'like', "%{$search}%")
                        ->orWhere('website', 'like', "%{$search}%")
                        ->orWhereHas('brands', fn (Builder $brand) => $brand->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('productTypes', fn (Builder $type) => $type->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('machineTypes', fn (Builder $machine) => $machine->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('paymentMethods', fn (Builder $payment) => $payment->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filtros['countryFilter'] ?? null, fn (Builder $q, string $country) => $q->where('country', $country))
            ->when($filtros['statusFilter'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filtros['brandFilter'] ?? null, fn (Builder $q, string $brandName) => $q->whereHas('brands', fn (Builder $brand) => $brand->where('name', $brandName)))
            ->when($filtros['productTypeFilter'] ?? null, fn (Builder $q, string $productType) => $q->whereHas('productTypes', fn (Builder $type) => $type->where('name', $productType)))
            ->when($filtros['machineTypeFilter'] ?? null, fn (Builder $q, string $machineType) => $q->whereHas('machineTypes', fn (Builder $machine) => $machine->where('name', $machineType)))
            ->when($filtros['paymentMethodFilter'] ?? null, fn (Builder $q, string $paymentMethod) => $q->whereHas('paymentMethods', fn (Builder $payment) => $payment->where('name', $paymentMethod)));
    }
}
