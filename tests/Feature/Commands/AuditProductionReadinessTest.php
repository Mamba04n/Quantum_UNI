<?php

namespace Tests\Feature\Commands;

use App\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_audit_is_read_only_and_can_fail_on_findings(): void
    {
        Storage::fake('private');
        Entity::query()->create([
            'name' => 'Cliente uno',
            'type' => 'cliente',
            'email' => 'duplicate@example.test',
        ]);
        Entity::query()->create([
            'name' => 'Cliente dos',
            'type' => 'cliente',
            'email' => ' DUPLICATE@example.test ',
        ]);

        $this->artisan('audit:production-readiness --fail-on-findings')
            ->assertFailed()
            ->expectsOutputToContain('duplicate_customer_emails');

        $this->assertSame(2, Entity::query()->count());
    }
}
