<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkspaceController;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use App\Support\WorkspaceOverview;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class AdminDashboardEntryTest extends TestCase
{
    public function test_admin_home_renders_workspace_index(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with(User::ROLE_ADMIN)->andReturn(true);
        $user->shouldReceive('canAccess')->with('dashboard')->andReturn(true);
        $user->shouldReceive('hasAnyPermission')->andReturn(true);
        $user->shouldReceive('can')->andReturn(false);
        $request = Request::create('/inicio');
        $request->setUserResolver(fn () => $user);

        $response = (new WorkspaceController)($request, new WorkspaceNavigation, new WorkspaceOverview);

        $this->assertInstanceOf(\Illuminate\Contracts\View\View::class, $response);
        $this->assertSame('workspaces.index', $response->name());
    }
}
