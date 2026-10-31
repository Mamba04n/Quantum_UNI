<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = collect([
            'dispatch_authorization.view' => 'Ver solicitudes de salida parcial',
            'dispatch_authorization.approve' => 'Autorizar salidas parciales',
        ])->map(fn (string $name, string $slug) => Permission::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'module' => 'Order Status']
        )->id);

        Role::query()
            ->whereIn('slug', ['admin', 'administrador', 'operaciones'])
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }

    public function down(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', ['dispatch_authorization.view', 'dispatch_authorization.approve'])
            ->pluck('id');

        Role::query()->each(fn (Role $role) => $role->permissions()->detach($permissionIds));
        Permission::query()->whereIn('id', $permissionIds)->delete();
    }
};
