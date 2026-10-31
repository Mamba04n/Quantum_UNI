<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileActivityTest extends TestCase
{
    public function test_profile_returns_only_five_latest_actions_of_the_authenticated_user(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module');
            $table->string('description');
            $table->timestamps();
        });
        foreach (range(1, 8) as $i) {
            DB::table('activity_logs')->insert([
                'user_id' => 101, 'module' => 'ventas', 'description' => 'Own activity '.$i,
                'created_at' => now()->subMinutes(9 - $i), 'updated_at' => now(),
            ]);
        }
        DB::table('activity_logs')->insert(['user_id' => 202, 'module' => 'finance',
            'description' => 'Other user private activity', 'created_at' => now(), 'updated_at' => now()]);
        $user = (new User)->forceFill(['id' => 101]);
        $request = Request::create('/profile');
        $request->setUserResolver(fn () => $user);
        $activity = (new ProfileController)($request)->getData()['recentActivity'];
        $this->assertCount(5, $activity);
        $this->assertSame(['Own activity 8', 'Own activity 7', 'Own activity 6', 'Own activity 5', 'Own activity 4'], $activity->pluck('description')->all());
    }
}
