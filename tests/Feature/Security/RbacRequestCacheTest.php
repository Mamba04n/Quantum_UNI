<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacRequestCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_resolved_once_per_request(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create([
            'slug' => 'security.cached',
            'name' => 'Security cached',
            'module' => 'security',
        ]);
        $user->syncPermissions([$permission->id]);

        $this->assertTrue($user->hasPermissionTo('security.cached'));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($user->hasPermissionTo('security.cached'));
        $this->assertTrue($user->hasAnyPermission(['missing', 'security.cached']));
        $this->assertFalse($user->hasRole(User::ROLE_ADMIN));

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_permission_revocation_invalidates_the_current_request_cache(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create([
            'slug' => 'security.revocable',
            'name' => 'Security revocable',
            'module' => 'security',
        ]);
        $user->syncPermissions([$permission->id]);
        $this->assertTrue($user->hasPermissionTo('security.revocable'));

        $user->syncPermissions([]);

        $this->assertFalse($user->hasPermissionTo('security.revocable'));
    }

    public function test_role_reassignment_invalidates_cached_roles_and_permissions(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->create([
            'slug' => 'security.from-role',
            'name' => 'Security from role',
            'module' => 'security',
        ]);
        $role = Role::query()->create(['slug' => 'temporary-role', 'name' => 'Temporary role']);
        $role->permissions()->sync([$permission->id]);
        $user->syncRoles([$role->id]);

        $this->assertTrue($user->hasRole('temporary-role'));
        $this->assertTrue($user->hasPermissionTo('security.from-role'));

        $user->syncRoles([]);

        $this->assertFalse($user->hasRole('temporary-role'));
        $this->assertFalse($user->hasPermissionTo('security.from-role'));
    }
}
