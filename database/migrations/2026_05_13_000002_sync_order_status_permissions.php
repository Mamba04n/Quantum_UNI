<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'order_status.view' => 'Order Status',
            'order_status.manage' => 'Order Status',
            'order_status.force_transition' => 'Order Status',
            'purchase_order.generate_pdf' => 'Compras',
            'supplier_confirmation.upload' => 'Compras',
            'shipment.upload' => 'Compras',
            'supplier_cancellation.register' => 'Compras',
            'replacement.request' => 'Compras',
            'replacement.select_supplier' => 'Compras',
            'replacement.approve' => 'Compras',
            'incoming_orders.view' => 'Bodega',
            'warehouse_receipt.register' => 'Bodega',
            'kardex.entry_from_order' => 'Bodega',
            'kardex.output_from_order' => 'Bodega',
            'logistics.upload_document' => 'Logistica',
            'delivery.register' => 'Logistica',
            'delivery.confirm' => 'Logistica',
            'client_delivery.view' => 'Ventas',
            'delivery_status.view' => 'Ventas',
        ];

        $permissionIds = collect($permissions)->map(function (string $module, string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace('.', ' ')->headline()->toString(), 'module' => $module]
            )->id;
        });

        Role::whereIn('slug', ['admin', 'administrador'])->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds)
        );
    }

    public function down(): void
    {
        //
    }
};
