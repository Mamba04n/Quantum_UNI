<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos académicos solo se cargan en local/testing.');
        }
        $password = (string) config('quantum_uni.admin_password');
        if (strlen($password) < 12) {
            throw new \RuntimeException('Define QUANTUM_UNI_ADMIN_PASSWORD con al menos 12 caracteres.');
        }
        $admin = User::updateOrCreate(
            ['email' => config('quantum_uni.admin_email')],
            ['name' => 'Administrador académico', 'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN, 'is_active' => true, 'email_verified_at' => now()],
        );
        $role = Role::where('slug', User::ROLE_ADMIN)->firstOrFail();
        $admin->syncRoles([$role->id]);
        $this->call(AcademicCaseSeeder::class);
    }
}
