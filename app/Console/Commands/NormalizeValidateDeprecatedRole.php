<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class NormalizeValidateDeprecatedRole extends Command
{
    protected $signature = 'normalization:validate-deprecated-role';
    protected $description = 'Validate that all users have roles in role_user before dropping users.role';

    public function handle()
    {
        $this->info('Validating user roles for deprecation of users.role...');

        $users = User::all();
        $missing = 0;

        foreach ($users as $user) {
            if ($user->roles()->count() === 0) {
                $this->warn("User ID {$user->id} ({$user->email}) has NO roles in role_user pivot table!");
                $missing++;
            }
        }

        if ($missing > 0) {
            $this->error("Found $missing users without roles in pivot table. DO NOT DROP users.role yet.");
        } else {
            $this->info("All users have roles in pivot table. It is safe to start deprecating users.role from code.");
        }
    }
}
