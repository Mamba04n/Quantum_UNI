<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * E2E Visual Seeder — Creates test users and base entities for Playwright E2E tests.
 *
 * This seeder is idempotent (uses updateOrCreate) so it can be run multiple times
 * without creating duplicates.
 *
 * Users created:
 *   admin@quantum.test      — Admin (full access)
 *   ventas@quantum.test     — Ventas
 *   compras@quantum.test    — Compras
 *   finanzas@quantum.test   — Agente de Finanzas
 *   bodega@quantum.test     — Bodega
 *   soporte@quantum.test    — Soporte
 *   cliente.test@quantum.test — Cliente Tienda (portal externo)
 *
 * Password for all: Password123!
 */
class E2EVisualSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $users = [
            ['name' => 'E2E Admin',         'email' => 'admin@quantum.test',         'role' => User::ROLE_ADMIN],
            ['name' => 'E2E Ventas',         'email' => 'ventas@quantum.test',        'role' => User::ROLE_VENTAS],
            ['name' => 'E2E Compras',        'email' => 'compras@quantum.test',       'role' => User::ROLE_COMPRAS],
            ['name' => 'E2E Finanzas',       'email' => 'finanzas@quantum.test',      'role' => User::ROLE_FINANZAS],
            ['name' => 'E2E Bodega',         'email' => 'bodega@quantum.test',        'role' => User::ROLE_BODEGA],
            ['name' => 'E2E Soporte',        'email' => 'soporte@quantum.test',       'role' => User::ROLE_SUPPORT],
            ['name' => 'E2E Cliente',        'email' => 'cliente.test@quantum.test',  'role' => User::ROLE_CLIENTE_TIENDA],
        ];

        foreach ($users as $seedUser) {
            $roleConfig     = config("rbac.roles.{$seedUser['role']}", []);
            $permissionSlugs = $roleConfig['permissions'] ?? [];

            $permissionIds = collect($permissionSlugs)->map(function (string $permission) {
                return Permission::firstOrCreate(
                    ['slug' => $permission],
                    ['name' => $permission, 'module' => str($permission)->before('.')->toString()]
                )->id;
            })->all();

            $role = Role::firstOrCreate(
                ['slug' => $seedUser['role']],
                ['name' => $roleConfig['name'] ?? User::$roles[$seedUser['role']] ?? ucfirst($seedUser['role'])]
            );
            $role->permissions()->sync($permissionIds);

            $user = User::updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name'               => $seedUser['name'],
                    'role'               => $seedUser['role'],
                    'password'           => Hash::make(self::PASSWORD),
                    'is_active'          => true,
                    'email_verified_at'  => now(),
                ]
            );

            $user->roles()->sync([$role->id]);
            $user->permissions()->sync([]);
        }

        // ── Base supplier entity ───────────────────────────────────────────────
        Entity::updateOrCreate(
            ['email' => 'proveedor.e2e@quantum.test'],
            [
                'name'         => 'Proveedor E2E ACSIS',
                'type'         => 'proveedor',
                'status'       => 'activo',
                'contact_name' => 'Contacto Proveedor E2E',
                'phone_mobile' => '+505 8888 0000',
                'country'      => 'Estados Unidos',
                'website'      => 'https://proveedor-e2e.test',
            ]
        );

        // ── Base customer entity ───────────────────────────────────────────────
        Entity::updateOrCreate(
            ['email' => 'cliente.e2e@quantum.test'],
            [
                'name'         => 'Cliente E2E Minera Norte',
                'type'         => 'cliente',
                'status'       => 'activo',
                'contact_name' => 'Contacto Cliente E2E',
                'phone_mobile' => '+505 7777 0000',
                'address'      => 'Rotonda Jean Paul Genie, Managua',
                'country'      => 'Nicaragua',
            ]
        );

        // ── Agroservicios San Miguel (datos del plan de pruebas) ──────────────
        Entity::updateOrCreate(
            ['email' => 'carlos@agrosanmiguel.com'],
            [
                'name'         => 'Agroservicios San Miguel',
                'type'         => 'cliente',
                'status'       => 'activo',
                'contact_name' => 'Carlos Ramírez',
                'phone_mobile' => '+505 8888 1122',
                'address'      => 'Rivas, Nicaragua',
                'country'      => 'Nicaragua',
            ]
        );

        // ── ACSIS Parts (proveedor USA) ────────────────────────────────────────
        Entity::updateOrCreate(
            ['email' => 'sales@acsisparts.com'],
            [
                'name'         => 'ACSIS Parts',
                'type'         => 'proveedor',
                'status'       => 'activo',
                'contact_name' => 'Michael Smith',
                'phone_mobile' => '+1 305 555 8822',
                'country'      => 'Estados Unidos',
            ]
        );

        // ── KB Repuestos (proveedor Corea) ────────────────────────────────────
        Entity::updateOrCreate(
            ['email' => 'sales@kbparts.kr'],
            [
                'name'         => 'KB Repuestos',
                'type'         => 'proveedor',
                'status'       => 'activo',
                'contact_name' => 'Kim Park',
                'phone_mobile' => '+82 10 5555 9090',
                'country'      => 'Corea del Sur',
            ]
        );
    }
}
