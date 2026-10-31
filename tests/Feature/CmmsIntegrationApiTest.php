<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IntegrationClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmmsIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(array $abilities = ['integration:read', 'users:read']): string
    {
        $client = IntegrationClient::create(['name' => (string) Str::uuid(), 'is_active' => true]);

        return $client->createToken('test', $abilities, now()->addHour())->plainTextToken;
    }

    public function test_requires_machine_bearer_even_with_user_session(): void
    {
        $this->getJson('/api/v1/integration/capabilities')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/integration/capabilities')->assertUnauthorized();
        $personal = $user->createToken('personal', ['integration:read'], now()->addHour())->plainTextToken;
        $this->withToken($personal)->getJson('/api/v1/integration/capabilities')->assertUnauthorized();
    }

    public function test_capabilities_are_real_and_traceable(): void
    {
        $correlation = (string) Str::uuid();
        $r = $this->withToken($this->token())->withHeader('X-Correlation-ID', $correlation)
            ->getJson('/api/v1/integration/capabilities')->assertOk()
            ->assertJsonPath('data.resources', ['capabilities', 'users', 'customers', 'assets', 'employees'])
            ->assertHeader('X-Correlation-ID', $correlation);
        $this->assertTrue(Str::isUuid($r->json('meta.request_id')));
        $this->assertSame('integration', AuditLog::latest('id')->first()->metadata['actor_type']);
    }

    public function test_identity_exposes_only_contract_fields(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $r = $this->withToken($this->token())->getJson('/api/v1/integration/users/'.$user->id)
            ->assertOk()->assertJsonPath('data.id', (string) $user->id)->assertJsonPath('data.active', false);
        $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'active', 'updated_at'], array_keys($r->json('data')));
        $this->withToken($this->token())->getJson('/api/v1/integration/users/999999')->assertNotFound();
    }

    public function test_missing_and_wildcard_abilities_are_rejected(): void
    {
        $this->withToken($this->token(['users:read']))->getJson('/api/v1/integration/capabilities')->assertForbidden();
        $this->withToken($this->token(['*']))->getJson('/api/v1/integration/capabilities')->assertForbidden();
    }

    public function test_expired_revoked_and_inactive_credentials_are_rejected(): void
    {
        $client = IntegrationClient::create(['name' => 'expiry']);
        $token = $client->createToken('expired', ['integration:read'], now()->subMinute());
        $this->withToken($token->plainTextToken)->getJson('/api/v1/integration/capabilities')->assertUnauthorized();
        $token = $client->createToken('revoked', ['integration:read'], now()->addHour());
        $token->accessToken->delete();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/integration/capabilities')->assertUnauthorized();
        $token = $client->createToken('inactive', ['integration:read'], now()->addHour());
        $client->update(['is_active' => false]);
        $this->withToken($token->plainTextToken)->getJson('/api/v1/integration/capabilities')->assertUnauthorized();
    }

    public function test_consumer_rate_limit_returns_retry_after(): void
    {
        $client = IntegrationClient::create(['name' => 'limited']);
        $token = $client->createToken('limited', ['integration:read'], now()->addHour());
        $key = 'integration:client:'.$client->id;
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->withToken($token->plainTextToken)->getJson('/api/v1/integration/capabilities')
            ->assertStatus(429)->assertHeader('Retry-After');
        RateLimiter::clear($key);
    }
}
