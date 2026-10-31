<?php

namespace Tests\Feature\Security;

use App\Models\ClientMachine;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerResourceIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_download_own_machine_image(): void
    {
        Storage::fake('private');
        [$owner, $machine] = $this->machineForCustomer('machine-owner');

        $response = $this->actingAs($owner)->get(route('private.client-machine-image', $machine));

        $response->assertOk();
        $this->assertSame('machine-owner', $response->streamedContent());
    }

    public function test_changing_machine_id_cannot_expose_another_customers_image(): void
    {
        Storage::fake('private');
        [, $machine] = $this->machineForCustomer('confidential-machine');
        $attacker = $this->customer();

        $this->actingAs($attacker)
            ->get(route('private.client-machine-image', $machine))
            ->assertForbidden();
    }

    public function test_unknown_machine_id_returns_not_found(): void
    {
        $this->actingAs($this->customer())
            ->get(route('private.client-machine-image', ['machine' => 999999]))
            ->assertNotFound();
    }

    public function test_request_creation_permission_does_not_grant_global_machine_read_access(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'customer_request.create'],
            ['name' => 'Crear solicitud', 'module' => 'sales']
        );
        $user->syncPermissions([$permission->id]);

        $this->assertFalse($user->can('viewAny', ClientMachine::class));
    }

    /** @return array{User, ClientMachine} */
    private function machineForCustomer(string $contents): array
    {
        $customer = $this->customer();
        $entity = Entity::query()->create([
            'name' => 'Cliente '.$customer->id,
            'type' => 'cliente',
            'user_id' => $customer->id,
        ]);
        $path = 'client-machines/'.$customer->id.'/machine.jpg';
        Storage::disk('private')->put($path, $contents);

        $machine = ClientMachine::query()->create([
            'entity_id' => $entity->id,
            'name' => 'Excavadora',
            'image_path' => $path,
        ]);

        return [$customer, $machine];
    }

    private function customer(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => User::ROLE_CLIENTE_TIENDA],
            ['name' => 'Cliente tienda']
        );
        $user = User::factory()->create();
        $user->syncRoles([$role->id]);

        return $user;
    }
}
