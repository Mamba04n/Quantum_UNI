<?php

namespace Tests\Feature\Configuration;

use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NicaraguaTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_and_mysql_session_use_nicaragua_time(): void
    {
        $this->assertSame('America/Managua', config('app.timezone'));
        $this->assertSame('-06:00', DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone);
    }

    public function test_timestamp_round_trip_preserves_the_real_utc_instant(): void
    {
        $localTime = CarbonImmutable::parse('2026-08-02 00:30:00', 'America/Managua');
        $this->travelTo($localTime);

        $user = User::factory()->create();
        $stored = DB::selectOne(
            "SELECT DATE_FORMAT(CONVERT_TZ(created_at, @@session.time_zone, '+00:00'), '%Y-%m-%d %H:%i:%s') AS utc_created_at FROM users WHERE id = ?",
            [$user->id]
        );

        $this->assertSame('2026-08-02 06:30:00', $stored->utc_created_at);
        $this->assertSame('2026-08-02 00:30:00', $user->created_at?->format('Y-m-d H:i:s'));
    }

    public function test_due_dates_change_at_nicaragua_midnight_not_utc_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-02 00:30:00', 'America/Managua'));
        $entity = Entity::query()->create(['name' => 'Cliente', 'type' => 'cliente']);

        $yesterday = AccountReceivable::query()->create([
            'entity_id' => $entity->id,
            'concept' => 'Vencida',
            'total_amount' => 100,
            'due_date' => '2026-08-01',
            'status' => 'pendiente',
        ]);
        $today = AccountReceivable::query()->create([
            'entity_id' => $entity->id,
            'concept' => 'Vence hoy',
            'total_amount' => 100,
            'due_date' => '2026-08-02',
            'status' => 'pendiente',
        ]);

        $this->assertSame('2026-08-02', now()->toDateString());
        $this->assertTrue($yesterday->isOverdue());
        $this->assertFalse($today->isOverdue());
    }
}
