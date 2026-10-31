<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PublicRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('public-registration:ip:127.0.0.1');
    }

    public function test_public_registration_creates_a_pending_customer_and_sends_verification(): void
    {
        Notification::fake();

        Volt::test('pages.auth.register')
            ->set('name', 'Cliente Seguro')
            ->set('email', 'cliente.seguro@example.test')
            ->set('phone', '+505 8888 8888')
            ->set('address', 'Managua, Nicaragua')
            ->set('password', 'Segura-2026!Quantum')
            ->set('password_confirmation', 'Segura-2026!Quantum')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice', absolute: false));

        $user = User::where('email', 'cliente.seguro@example.test')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame([User::ROLE_CLIENTE_TIENDA], $user->roleSlugs());
        $this->assertDatabaseHas('entities', [
            'user_id' => $user->id,
            'type' => 'cliente',
        ]);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->actingAs($user)->get(route('customer.portal'))
            ->assertRedirect(route('verification.notice'));

        $user->markEmailAsVerified();
        $this->actingAs($user)->get(route('customer.portal'))->assertOk();
    }

    public function test_weak_password_is_rejected(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Cliente Débil')
            ->set('email', 'debil@example.test')
            ->set('phone', '+505 8888 1111')
            ->set('address', 'León, Nicaragua')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'debil@example.test']);
    }

    public function test_honeypot_blocks_automated_registration(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Bot')
            ->set('email', 'bot@example.test')
            ->set('phone', '+505 8888 2222')
            ->set('address', 'Dirección automática')
            ->set('password', 'Segura-2026!Quantum')
            ->set('password_confirmation', 'Segura-2026!Quantum')
            ->set('website', 'https://spam.example')
            ->call('register')
            ->assertSet('errorMessage', 'No fue posible completar el registro. Intenta nuevamente.');

        $this->assertDatabaseMissing('users', ['email' => 'bot@example.test']);
    }

    public function test_registration_is_rate_limited_inside_the_livewire_action(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('email', 'limited@example.test')
            ->set('website', 'bot');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('register');
        }

        $component->call('register');

        $this->assertStringContainsString('Demasiados intentos', $component->get('errorMessage'));
    }

    public function test_existing_internal_or_inactive_user_is_never_converted_or_reactivated(): void
    {
        $user = User::factory()->create([
            'email' => 'interno@example.test',
            'is_active' => false,
        ]);
        $adminRole = Role::where('slug', User::ROLE_ADMIN)->firstOrFail();
        $user->roles()->sync([$adminRole->id]);

        try {
            app(CustomerService::class)->registerPortalCustomer([
                'name' => 'Intento de conversión',
                'email' => $user->email,
                'phone' => '+505 8888 3333',
                'address' => 'Managua',
                'password' => 'Segura-2026!Quantum',
            ]);
            $this->fail('El usuario interno no debe convertirse en cliente.');
        } catch (ValidationException) {
            $user->refresh();
            $this->assertFalse($user->is_active);
            $this->assertSame([User::ROLE_ADMIN], $user->roleSlugs());
            $this->assertFalse(Entity::where('user_id', $user->id)->exists());
        }
    }

    public function test_technical_registration_failure_is_logged_but_only_an_incident_id_is_exposed(): void
    {
        Role::where('slug', User::ROLE_CLIENTE_TIENDA)->delete();
        Log::spy();

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Cliente sin catálogo')
            ->set('email', 'incidente@example.test')
            ->set('phone', '+505 8888 4444')
            ->set('address', 'Managua')
            ->set('password', 'Segura-2026!Quantum')
            ->set('password_confirmation', 'Segura-2026!Quantum')
            ->call('register');

        $message = $component->get('errorMessage');
        $this->assertMatchesRegularExpression(
            '/^No fue posible completar el registro\. Incidente: [0-9A-HJKMNP-TV-Z]{26}\.$/',
            $message,
        );
        $this->assertStringNotContainsString('Role', $message);
        $this->assertDatabaseMissing('users', ['email' => 'incidente@example.test']);

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Fallo en registro público.'
                && isset($context['incident_id'], $context['exception']),
        );
    }
}
