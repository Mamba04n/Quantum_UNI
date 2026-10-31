<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $rolePermissions = [
            'bodega' => [
                'order_status.view',
                'incoming_orders.view',
                'warehouse_receipt.register',
                'kardex.entry_from_order',
                'kardex.output_from_order',
                'delivery_status.view',
            ],
            'compras' => [
                'order_status.view',
                'order_status.manage',
                'purchase_order.generate_pdf',
                'supplier_confirmation.upload',
                'shipment.upload',
                'supplier_cancellation.register',
                'replacement.request',
                'replacement.select_supplier',
            ],
            'operaciones' => [
                'order_status.view',
                'logistics.upload_document',
                'delivery.register',
                'delivery.confirm',
                'delivery_status.view',
            ],
            'ventas' => [
                'order_status.view',
                'client_delivery.view',
                'delivery_status.view',
            ],
            'admin' => [
                'order_status.view',
                'order_status.manage',
                'order_status.force_transition',
                'purchase_order.generate_pdf',
                'supplier_confirmation.upload',
                'shipment.upload',
                'supplier_cancellation.register',
                'replacement.request',
                'replacement.select_supplier',
                'replacement.approve',
                'incoming_orders.view',
                'warehouse_receipt.register',
                'kardex.entry_from_order',
                'kardex.output_from_order',
                'logistics.upload_document',
                'delivery.register',
                'delivery.confirm',
                'client_delivery.view',
                'delivery_status.view',
            ],
            'administrador' => [
                'order_status.view',
                'order_status.manage',
                'order_status.force_transition',
                'purchase_order.generate_pdf',
                'supplier_confirmation.upload',
                'shipment.upload',
                'supplier_cancellation.register',
                'replacement.request',
                'replacement.select_supplier',
                'replacement.approve',
                'incoming_orders.view',
                'warehouse_receipt.register',
                'kardex.entry_from_order',
                'kardex.output_from_order',
                'logistics.upload_document',
                'delivery.register',
                'delivery.confirm',
                'client_delivery.view',
                'delivery_status.view',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissions) {
            $role = Role::where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = collect($permissions)->map(function (string $slug) {
                return Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => str($slug)->replace('.', ' ')->headline()->toString(), 'module' => str($slug)->before('.')->headline()->toString()]
                )->id;
            });

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    public function down(): void
    {
        //
    }
};
