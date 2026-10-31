<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('module')->index();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'permission_id']);
        });

        $this->seedDefaultAccess();
        $this->backfillUserRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    private function seedDefaultAccess(): void
    {
        $now = now();

        foreach (config('rbac.permissions', []) as $module => $permissions) {
            foreach ($permissions as $slug => $name) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $slug],
                    ['name' => $name, 'module' => $module, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        foreach (config('rbac.roles', []) as $slug => $roleConfig) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $roleConfig['name'],
                    'description' => $roleConfig['description'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', $roleConfig['permissions'] ?? [])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    private function backfillUserRoles(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $now = now();

        DB::table('users')
            ->select(['id', 'role'])
            ->whereNotNull('role')
            ->orderBy('id')
            ->each(function ($user) use ($now) {
                $roleId = DB::table('roles')->where('slug', $user->role)->value('id');

                if (! $roleId) {
                    return;
                }

                DB::table('role_user')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            });
    }
};
