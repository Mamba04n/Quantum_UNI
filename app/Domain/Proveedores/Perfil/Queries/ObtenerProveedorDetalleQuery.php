<?php

namespace App\Domain\Proveedores\Perfil\Queries;

use App\Models\Entity;

class ObtenerProveedorDetalleQuery
{
    public function ejecutar(?int $supplierId, bool $puedeVerDatosBancarios = false): ?Entity
    {
        if (! $supplierId) {
            return null;
        }

        $relaciones = [
            'brands',
            'productTypes',
            'machineTypes',
            'paymentMethods',
            'contacts',
            'supplierBids.quotationRequest',
            'supplierBidResponses.quotationRequest',
            'purchaseOrders',
            'accountPayables',
        ];

        if ($puedeVerDatosBancarios) {
            $relaciones[] = 'bankAccounts';
        }

        return Entity::with($relaciones)
            ->where('type', 'proveedor')
            ->find($supplierId);
    }
}
