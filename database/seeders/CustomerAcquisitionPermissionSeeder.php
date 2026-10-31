<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CustomerAcquisitionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('rbac.permissions.prospects') as $slug => $name) {
            $p = Permission::firstOrCreate(['slug' => $slug], ['name' => $name, 'module' => 'prospects']);
            foreach (Role::whereIn('slug', ['admin', 'ventas'])->get() as $role) {
                if ($role->slug === 'ventas' && in_array($slug, ['prospects.view_all', 'prospects.reassign'])) {
                    continue;
                }
                $role->permissions()->syncWithoutDetaching([$p->id]);
            }
        }
    }
}
