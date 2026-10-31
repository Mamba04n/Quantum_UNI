<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use App\Providers\RouteServiceProvider;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_successful_logins_do_not_consume_the_failed_attempt_budget(): void
    {
        $password = 'ValidPassword123!';
        $user = User::factory()->create(['password' => Hash::make($password)]);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => $password,
            ])->assertRedirect(RouteServiceProvider::HOME);

            $this->post(route('logout'))->assertRedirect('/');
        }
    }

    public function test_login_does_not_publish_the_user_id_in_a_legacy_cookie(): void
    {
        $password = 'ValidPassword123!';
        $user = User::factory()->create(['password' => Hash::make($password)]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(RouteServiceProvider::HOME)
            ->assertCookieMissing('storefront_user_id');
    }

    public function test_sixth_failed_attempt_for_the_same_identity_is_throttled(): void
    {
        $credentials = [
            'email' => 'attacked@example.test',
            'password' => 'WrongPassword123!',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post(route('login'), $credentials)
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');
        }

        $this->from('/login')->post(route('login'), $credentials)
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email', 'Demasiados intentos de acceso. Inténtalo de nuevo en 60 segundos.');
    }
}
