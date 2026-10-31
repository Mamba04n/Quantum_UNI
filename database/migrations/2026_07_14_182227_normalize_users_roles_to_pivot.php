<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Data Migration: Ensure every user has their text role mapped to the role_user pivot
        $users = DB::table('users')->get();
        
        foreach ($users as $user) {
            if (empty($user->role)) {
                continue;
            }
            
            // Find or create the role
            $roleId = DB::table('roles')->where('slug', $user->role)->value('id');
            
            if (!$roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'slug' => $user->role,
                    'name' => Str::title(str_replace('_', ' ', $user->role)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Assign to role_user if not exists
            $exists = DB::table('role_user')
                        ->where('user_id', $user->id)
                        ->where('role_id', $roleId)
                        ->exists();
                        
            if (!$exists) {
                DB::table('role_user')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Schema modification: Deprecate the column
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->nullable()->default(null)->comment('Deprecated: use role_user pivot table instead.')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pivot', function (Blueprint $table) {
            //
        });
    }
};
