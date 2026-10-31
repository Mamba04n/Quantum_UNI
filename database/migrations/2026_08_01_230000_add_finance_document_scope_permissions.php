<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $viewAll = Permission::updateOrCreate(
            ['slug' => 'finance.documents.view_all'],
            ['name' => 'Ver todos los documentos financieros', 'module' => 'finance']
        );

        Permission::updateOrCreate(
            ['slug' => 'finance.documents.view_assigned'],
            ['name' => 'Ver documentos financieros asignados', 'module' => 'finance']
        );

        Role::whereIn('slug', ['agente_finanzas', 'finanzas'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$viewAll->id]));
    }

    public function down(): void
    {
        Permission::whereIn('slug', [
            'finance.documents.view_all',
            'finance.documents.view_assigned',
        ])->delete();
    }
};
