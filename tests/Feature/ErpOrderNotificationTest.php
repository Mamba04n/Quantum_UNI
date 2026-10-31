<?php

namespace Tests\Feature;

use App\Livewire\Layout\ErpNotifications;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ErpOrderNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_order_creation_and_status_change_notify_customer_and_appear_for_authorized_staff(): void
    {
        Notification::fake();
        $order = SaleOrder::factory()->create(['client_email' => 'order-notification@example.test', 'status' => 'generated']);
        Notification::assertSentTo($order, OrderStatusUpdated::class);
        $order->update(['status' => 'confirmed']);
        Notification::assertSentToTimes($order, OrderStatusUpdated::class, 2);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => User::ROLE_ADMIN], ['name' => 'Administrador']);
        $admin->roles()->attach($role);
        Livewire::actingAs($admin)->test(ErpNotifications::class)->assertSee('Órdenes recientes')->assertSee($order->reference);
        $employee = User::factory()->create();
        Livewire::actingAs($employee)->test(ErpNotifications::class)->assertDontSee($order->reference);
    }

    public function test_customer_cannot_mount_internal_notifications(): void
    {
        $customer = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => User::ROLE_CLIENTE_TIENDA], ['name' => 'Cliente']);
        $customer->roles()->attach($role);
        Livewire::actingAs($customer)->test(ErpNotifications::class)->assertForbidden();
    }
}
