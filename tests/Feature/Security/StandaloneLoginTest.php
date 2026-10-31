<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class StandaloneLoginTest extends TestCase
{
    public function test_login_does_not_depend_on_a_running_asset_server(): void
    {
        Http::preventStrayRequests();
        $hotFile = tempnam(sys_get_temp_dir(), 'login-hot-');
        file_put_contents($hotFile, 'http://127.0.0.1:59999');
        Vite::useHotFile($hotFile);

        try {
            $this->get('/login')->assertOk()
                ->assertSee('css/login.css', false)
                ->assertDontSee('127.0.0.1:59999', false)
                ->assertDontSee('@vite/client', false)
                ->assertSee('name="password"', false);
            Http::assertNothingSent();
        } finally {
            unlink($hotFile);
        }
    }
}
