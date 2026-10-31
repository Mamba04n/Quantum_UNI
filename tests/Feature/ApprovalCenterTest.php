<?php

namespace Tests\Feature;

use App\Livewire\Administracion\ApprovalManager;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_one_center_with_only_the_approval_types_they_can_access(): void
    {
        $user = User::factory()->create();
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'finance.view'],
            ['name' => 'Ver finanzas', 'module' => 'finance'],
        );
        $user->permissions()->sync([$permission->id]);

        $this->actingAs($user)
            ->get(route('admin.approvals'))
            ->assertOk();

        Livewire::actingAs($user)
            ->test(ApprovalManager::class)
            ->assertSet('managerSection', 'overview')
            ->assertSee('Centro de aprobaciones')
            ->assertSee('Cambios financieros')
            ->assertDontSee('RFQ de proveedores')
            ->assertDontSee('Órdenes de compra');
    }

    public function test_unauthorized_user_cannot_approve_a_stage_change(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ApprovalManager::class)
            ->call('approveStageChange', 999)
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_reject_a_stage_change(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ApprovalManager::class)
            ->set('stageChangeReviewNotes.999', 'Motivo válido')
            ->call('rejectStageChange', 999)
            ->assertForbidden();
    }
}
