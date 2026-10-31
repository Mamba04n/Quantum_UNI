<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'purchase_order_document.view' => 'Documento Compra',
            'purchase_order_document.edit' => 'Documento Compra',
            'purchase_order_document.generate_pdf' => 'Documento Compra',
            'purchase_order_document.regenerate_pdf' => 'Documento Compra',
            'purchase_order_document.view_internal_comments' => 'Documento Compra',
            'purchase_order_document.view_costs' => 'Documento Compra',
        ];

        $ids = collect($permissions)->map(fn (string $module, string $slug) => Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('.', ' ')->headline()->toString(), 'module' => $module]
        )->id);

        Role::whereIn('slug', ['admin', 'administrador', 'compras'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));

        $viewIds = Permission::whereIn('slug', ['purchase_order_document.view'])->pluck('id');
        Role::whereIn('slug', ['ventas', 'operaciones', 'agente_finanzas'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($viewIds));

        $costIds = Permission::whereIn('slug', ['purchase_order_document.view_costs'])->pluck('id');
        Role::whereIn('slug', ['agente_finanzas'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($costIds));
    }

    public function down(): void
    {
        //
    }
};
