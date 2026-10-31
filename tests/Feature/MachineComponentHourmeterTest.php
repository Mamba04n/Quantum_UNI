<?php

namespace Tests\Feature;

use App\Models\ClientMachine;
use App\Models\Entity;
use App\Services\MachineComponentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineComponentHourmeterTest extends TestCase
{
    use RefreshDatabase;

    public function test_hour_based_component_usage_is_derived_from_machine_hourmeter_and_last_replacement(): void
    {
        $client = Entity::create([
            'name' => 'Cliente Horometro',
            'type' => 'cliente',
        ]);

        $machine = ClientMachine::create([
            'entity_id' => $client->id,
            'machine_type' => 'Excavadora',
            'name' => 'Excavadora CAT 320',
            'hourmeter' => 1250,
            'hourmeter_date' => now()->toDateString(),
            'status' => 'activa',
        ]);

        $component = app(MachineComponentLifecycleService::class)->create([
            'client_machine_id' => $machine->id,
            'component_name' => 'Bateria',
            'part_number' => 'BAT-100',
            'quantity' => 1,
            'counter_type' => 'horas',
            'last_replacement_hourmeter' => 1000,
            'current_usage' => 0,
            'estimated_life' => 500,
        ]);

        $this->assertSame(250, (int) $component->current_usage);
        $this->assertSame(250, (int) $component->residual_life);
        $this->assertSame('bueno', $component->status);

        $updated = app(MachineComponentLifecycleService::class)->updateUsage($component->fresh('machine'), 1450, 'Lectura actual del equipo');

        $this->assertSame(1450, (int) $machine->fresh()->hourmeter);
        $this->assertSame(450, (int) $updated->current_usage);
        $this->assertSame(50, (int) $updated->residual_life);
        $this->assertSame('critico', $updated->status);
        $this->assertDatabaseHas('component_usage_logs', [
            'component_id' => $component->id,
            'previous_usage' => 250,
            'new_usage' => 450,
            'notes' => 'Lectura actual del equipo',
        ]);
    }
}
