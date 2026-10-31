<?php

namespace Tests\Feature;

use App\Domain\Finanzas\PaymentLinks\PaymentGateway;
use App\Domain\Finanzas\PaymentLinks\PaymentLinkService;
use App\Domain\Pedidos\OrderStatus\Actions\RegisterDelivery;
use App\Domain\Pedidos\OrderStatus\Actions\RegisterDeliveryDispatch;
use App\Domain\Pedidos\Tracking\Services\PublicTrackingService;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaymentLinksAndPublicTrackingTest extends TestCase
{
    // No RefreshDatabase: additive migrations are applied explicitly to the test DB.
    use DatabaseTransactions;

    private User $admin;

    private Entity $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('private');
        $this->admin = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $this->admin->roles()->attach($role);
        $this->actingAs($this->admin);
        $this->customer = Entity::create(['name' => 'PRIVATE CUSTOMER', 'type' => 'cliente', 'email' => 'private@example.test', 'rfc_ruc' => 'PRIVATE-RUC']);
        $this->product = Product::factory()->create(['name' => 'Filtro', 'description' => 'Filtro de aceite', 'sales_price' => '365.00', 'active' => true]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['issuance_key' => (string) Str::uuid(), 'customer_id' => $this->customer->id, 'description' => 'Cobro de prueba', 'internal_note' => 'PRIVATE INTERNAL NOTE', 'expires_at' => now()->addHours(72)->toDateTimeString(), 'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => '10.00'], ['product_id' => null, 'description' => 'Flete', 'quantity' => 1, 'unit_price' => '5.00']]], $overrides);
    }

    private function completed($link, string $amount = '25.00', string $currency = 'USD'): array
    {
        return ['id' => $link->provider_order_id, 'status' => 'COMPLETED', 'purchase_units' => [['custom_id' => $link->code, 'payments' => ['captures' => [['id' => 'CAPTURE123', 'status' => 'COMPLETED', 'amount' => ['value' => $amount, 'currency_code' => $currency]]]]]]];
    }

    public function test_snapshots_totals_and_public_privacy(): void
    {
        $link = app(PaymentLinkService::class)->create($this->admin, $this->data(['total' => '1.00']));
        $this->assertEquals('25.00', $link->total);
        $this->assertCount(2, $link->items);
        $this->product->update(['name' => 'Changed', 'description' => 'Changed', 'sales_price' => '1.00']);
        $this->assertEquals('Filtro de aceite', $link->items()->first()->description);
        $this->assertEquals('912.50', $link->receivable->total_amount);
        auth()->logout();
        $this->get($link->publicUrl())->assertOk()->assertSee('25.00')->assertDontSee('PRIVATE CUSTOMER')->assertDontSee('PRIVATE INTERNAL NOTE')->assertDontSee('PRIVATE-RUC')->assertDontSee('private@example.test');
    }

    public function test_product_image_is_preserved_after_catalogue_changes(): void
    {
        Storage::fake('public');
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aG1sAAAAASUVORK5CYII=');
        Storage::disk('public')->put('products/filter.png', $bytes);
        $this->product->update(['image_path' => 'products/filter.png']);
        $link = app(PaymentLinkService::class)->create($this->admin, $this->data());
        $item = $link->items()->whereNotNull('product_id')->first();
        $this->assertStringStartsWith('storage/payment-link-images/', $item->image_path);
        Storage::disk('public')->delete('products/filter.png');
        $this->product->update(['image_path' => null]);
        Storage::disk('public')->assertExists(substr($item->image_path, 8));
        auth()->logout();
        $this->get($link->publicUrl())->assertOk()->assertSee($item->image_url);
    }

    public function test_issuance_retry_returns_one_link_and_one_receivable(): void
    {
        $service = app(PaymentLinkService::class);
        $data = $this->data();
        $first = $service->create($this->admin, $data);
        $second = $service->create($this->admin, $data);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AccountReceivable::where('entity_id', $this->customer->id)->count());
        $this->expectException(ValidationException::class);
        $data['description'] = 'Different request';
        $service->create($this->admin, $data);
    }

    public function test_checkout_and_capture_requests_are_replay_safe(): void
    {
        $link = app(PaymentLinkService::class)->create($this->admin, $this->data());
        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createOrder')->once()->andReturn(['id' => 'ORDER123', 'status' => 'CREATED']);
        $this->app->instance(PaymentGateway::class, $gateway);
        $service = app(PaymentLinkService::class);
        $this->assertEquals('pending', $link->fresh()->effectiveStatus());
        $this->assertEquals('ORDER123', $service->checkout($link));
        $this->assertEquals('pending', $link->fresh()->effectiveStatus());
        $this->assertEquals('ORDER123', $service->checkout($link));
        $gateway->shouldReceive('queryOrder')->twice()->with('ORDER123')->andReturn(['id' => 'ORDER123', 'status' => 'APPROVED'], $this->completed($link->fresh()));
        $gateway->shouldReceive('capture')->once()->with('ORDER123', 'capture-'.$link->code)->andReturn(['id' => 'ORDER123', 'status' => 'COMPLETED']);
        $this->assertTrue($service->capture($link, 'ORDER123'));
        $this->assertTrue($service->capture($link, 'ORDER123'));
        $this->assertEquals(1, Payment::where('idempotency_key', 'paypal:CAPTURE123')->count());
    }

    public function test_payment_confirmation_waits_for_completed_capture(): void
    {
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data());
        $link->update(['provider_order_id' => 'ORDER123']);
        $order = $this->completed($link);
        $order['purchase_units'][0]['payments']['captures'][0]['status'] = 'PENDING';
        $this->assertFalse($service->confirm($link, $order));
        $this->assertNull($link->fresh()->payment_id);
    }

    public function test_external_capture_is_preserved_for_review_if_receivable_changed(): void
    {
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data());
        $link->update(['provider_order_id' => 'ORDER123']);
        $link->receivable->update(['paid_amount' => '912.50', 'status' => 'pagado']);
        $this->assertTrue($service->confirm($link, $this->completed($link)));
        $this->assertEquals('paid', $link->fresh()->status);
        $this->assertEquals('finance_application_requires_review', $link->fresh()->review_reason);
        $this->assertNull($link->fresh()->payment_id);
        $this->assertEquals('912.50', $link->receivable->fresh()->paid_amount);
    }

    public function test_tracker_code_is_unique_for_different_orders(): void
    {
        $service = app(PublicTrackingService::class);
        $a = $service->generate($this->admin, $this->warehouseOrder());
        $b = $service->generate($this->admin, $this->warehouseOrder());
        $this->assertNotEquals($a->public_tracking_code, $b->public_tracking_code);
        $this->expectException(QueryException::class);
        $b->update(['public_tracking_code' => $a->public_tracking_code]);
    }

    public function test_create_without_permission_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->expectException(HttpException::class);
        app(PaymentLinkService::class)->create($user, $this->data());
    }

    public function test_price_override_requires_permission(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['slug' => 'payment_links.create'], ['name' => 'Crear', 'module' => 'finance']);
        $user->permissions()->attach($permission);
        $this->expectException(HttpException::class);
        app(PaymentLinkService::class)->create($user, $this->data(['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '1.00']]]));
    }

    public function test_catalogue_price_without_override_is_allowed(): void
    {
        $user = User::factory()->create();
        $p = Permission::firstOrCreate(['slug' => 'payment_links.create'], ['name' => 'Crear', 'module' => 'finance']);
        $user->permissions()->attach($p);
        $link = app(PaymentLinkService::class)->create($user, $this->data(['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '10.00']]]));
        $this->assertEquals('10.00', $link->total);
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(PaymentLinkService::class)->create($this->admin, $this->data(['items' => [['product_id' => $this->product->id, 'quantity' => -1, 'unit_price' => '1.00']]]));
    }

    public function test_capture_replay_applies_one_payment_and_correct_balance(): void
    {
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data());
        $link->update(['provider_order_id' => 'ORDER123']);
        $order = $this->completed($link);
        $this->assertTrue($service->confirm($link, $order));
        $this->assertTrue($service->confirm($link, $order));
        $link->refresh();
        $this->assertNotNull($link->payment_id);
        $this->assertEquals('paid', $link->status);
        $this->assertEquals(1, Payment::where('idempotency_key', 'paypal:CAPTURE123')->count());
        $this->assertEquals('912.50', $link->receivable->paid_amount);
    }

    public function test_amount_and_currency_mismatches_do_not_pay(): void
    {
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data());
        $link->update(['provider_order_id' => 'ORDER123']);
        $this->assertFalse($service->confirm($link, $this->completed($link, '1.00')));
        $this->assertFalse($service->confirm($link, $this->completed($link, '25.00', 'EUR')));
        $this->assertNull($link->fresh()->payment_id);
        $this->assertEquals('0.00', $link->receivable->paid_amount);
    }

    public function test_expired_and_cancelled_links_cannot_start_checkout(): void
    {
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data());
        $service->cancel($this->admin, $link);
        $this->postJson(route('public.payment.order', $link->public_token))->assertUnprocessable();
        $link->update(['status' => 'pending', 'expires_at' => now()->subMinute()]);
        $this->postJson(route('public.payment.order', $link->public_token))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_cancel_permission_is_required(): void
    {
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data());
        $this->expectException(HttpException::class);
        $service->cancel(User::factory()->create(), $link);
    }

    public function test_signed_webhook_is_idempotent_and_invalid_signature_rejected(): void
    {
        $link = app(PaymentLinkService::class)->create($this->admin, $this->data());
        $link->update(['provider_order_id' => 'ORDER123']);
        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('verifyWebhook')->twice()->andReturn(true);
        $gateway->shouldReceive('queryOrder')->twice()->with('ORDER123')->andReturn($this->completed($link));
        $this->app->instance(PaymentGateway::class, $gateway);
        $event = ['id' => 'EVENT123', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER123']]]];
        auth()->logout();
        $this->postJson('/webhooks/paypal', $event)->assertOk();
        $this->postJson('/webhooks/paypal', $event)->assertOk();
        $this->assertEquals(1, Payment::where('idempotency_key', 'paypal:CAPTURE123')->count());
        $this->assertEquals(1, DB::table('payment_provider_events')->where('event_id', 'EVENT123')->count());
        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('verifyWebhook')->once()->andReturn(false);
        $this->app->instance(PaymentGateway::class, $gateway);
        $this->postJson('/webhooks/paypal', $event)->assertBadRequest();
    }

    private function warehouseOrder(): SaleOrder
    {
        $order = SaleOrder::withoutEvents(fn () => SaleOrder::factory()->create(['customer_id' => $this->customer->id, 'status' => 'confirmed', 'total_amount' => '25.00', 'currency' => 'USD']));
        $order->items()->create(['product_id' => $this->product->id, 'description' => 'Filtro', 'quantity' => 2, 'unit_price' => '10.00', 'total_price' => '20.00']);
        $status = OrderStatus::withoutEvents(fn () => OrderStatus::create(['sale_order_id' => $order->id, 'customer_id' => $this->customer->id]));
        OrderStatusItem::create(['order_status_id' => $status->id, 'product_id' => $this->product->id, 'part_name' => 'Filtro', 'part_number' => 'PART-TEST', 'quantity_ordered' => 2, 'quantity_kardex_entry' => 2, 'quantity_dispatched' => 0, 'quantity_delivered' => 0]);

        return $order;
    }

    public function test_tracking_reuses_tracker_and_preserves_private_timeline(): void
    {
        $order = $this->warehouseOrder();
        $service = app(PublicTrackingService::class);
        $tracker = $service->generate($this->admin, $order);
        $this->assertMatchesRegularExpression('/^QS-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{12}$/', $tracker->public_tracking_code);
        $this->assertEquals($tracker->id, $service->generate($this->admin, $order)->id);
        $tracker->activities()->create(['action' => 'internal', 'phase' => 'internal', 'description' => 'SECRET SUPPLIER NOTE']);
        $service->advance($this->admin, $tracker, 'preparing');
        $order->orderStatus->items()->update(['quantity_dispatched' => 2]);
        $service->advance($this->admin, $tracker, 'ready');
        $order->orderStatus->items()->update(['quantity_delivered' => 2]);
        $service->advance($this->admin, $tracker, 'in_transit');
        $this->assertNotNull($tracker->fresh()->public_delivered_at);
        $this->assertCount(4, $service->publicData($tracker->fresh())['events']);
        auth()->logout();
        $this->get(route('public.tracking', $tracker->public_tracking_code))->assertOk()->assertSee('Entregado')->assertDontSee('SECRET SUPPLIER NOTE')->assertDontSee('PRIVATE CUSTOMER')->assertDontSee('PRIVATE-RUC');
        $this->get('/seguimiento?code='.$tracker->public_tracking_code)->assertOk()->assertSee('Entregado');
    }

    public function test_tracking_dispatch_requires_real_dispatch_and_stale_state_is_rejected(): void
    {
        $order = $this->warehouseOrder();
        $service = app(PublicTrackingService::class);
        $tracker = $service->generate($this->admin, $order);
        $service->advance($this->admin, $tracker, 'preparing');
        try {
            $service->advance($this->admin, $tracker, 'preparing');
            $this->fail('Stale state accepted');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $service->advance($this->admin, $tracker, 'ready');
    }

    public function test_tracking_permission_is_required(): void
    {
        $order = $this->warehouseOrder();
        $this->expectException(HttpException::class);
        app(PublicTrackingService::class)->generate(User::factory()->create(), $order);
    }

    public function test_unknown_tracking_is_generic_and_rate_limited(): void
    {
        auth()->logout();
        $this->get('/seguimiento/QS-AAAAAAAAAAAA')->assertOk()->assertSee('No encontramos un pedido');
        for ($i = 0; $i < 32; $i++) {
            $response = $this->get('/seguimiento/QS-AAAAAAAAAAAA');
        }
        $response->assertStatus(429);
    }

    public function test_order_payment_uses_existing_receivable_and_supports_partial_balance(): void
    {
        $order = $this->warehouseOrder();
        $account = AccountReceivable::create(['entity_id' => $this->customer->id, 'sales_order_id' => $order->id, 'concept' => 'Orden', 'total_amount' => '1825.00', 'paid_amount' => '0.00', 'due_date' => now()->addWeek(), 'status' => 'pendiente', 'currency' => 'NIO']);
        $service = app(PaymentLinkService::class);
        $link = $service->create($this->admin, $this->data(['sale_order_id' => $order->id]));
        $this->assertEquals($account->id, $link->account_receivable_id);
        $link->update(['provider_order_id' => 'ORDER123']);
        $service->confirm($link, $this->completed($link));
        $this->assertEquals('912.50', $account->fresh()->paid_amount);
        $this->assertEquals('parcial', $account->fresh()->status);
        $trackingService = app(PublicTrackingService::class);
        $tracker = $trackingService->generate($this->admin, $order);
        $trackingService->advance($this->admin, $tracker, 'preparing');
        $item = $order->orderStatus->items()->first();
        $item->update(['quantity_kardex_output' => 2]);
        foreach (['delivery_logistics_document', 'delivery_confirmation'] as $type) {
            $order->orderStatus->documents()->create(['phase' => 'logistica_entrega', 'document_type' => $type, 'order_status_item_id' => $item->id, 'file_name' => 'Evidence.pdf', 'file_path' => 'test/evidence.pdf']);
        }
        app(RegisterDeliveryDispatch::class)->handle($item, 2, $this->admin);
        $trackingService->advance($this->admin, $tracker, 'ready');
        app(RegisterDelivery::class)->handle($item->fresh(), 2, $this->admin);
        $trackingService->advance($this->admin, $tracker, 'in_transit');
        $this->assertEquals('delivered', $tracker->fresh()->delivery_status);
        $this->assertEquals(2, $item->fresh()->quantity_delivered);
        auth()->logout();
        $this->get($link->publicUrl())->assertSee($tracker->public_tracking_code)->assertSee('Ver seguimiento');
    }
}
