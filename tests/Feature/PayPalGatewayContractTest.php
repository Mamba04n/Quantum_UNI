<?php

namespace Tests\Feature;

use App\Domain\Finanzas\PaymentLinks\PayPalGateway;
use App\Models\PaymentLink;
use App\Models\PaymentLinkItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalGatewayContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paypal.environment' => 'sandbox', 'services.paypal.client_id' => 'test-client', 'services.paypal.client_secret' => 'test-secret', 'services.paypal.webhook_id' => 'test-webhook']);
        Http::preventStrayRequests();
    }

    private function fake(array $response): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'test-access']), '*/v2/checkout/orders*' => Http::response($response), '*/v1/notifications/verify-webhook-signature' => Http::response($response)]);
    }

    public function test_create_order_uses_snapshot_amount_breakdown_and_idempotency_header(): void
    {
        $this->fake(['id' => 'ORDER123', 'status' => 'CREATED']);
        $link = new PaymentLink(['code' => 'PAY-TEST', 'public_token' => str_repeat('a', 64), 'currency' => 'USD', 'total' => '25.00']);
        $link->setRelation('items', collect([new PaymentLinkItem(['description' => 'Filtro', 'quantity' => 2, 'unit_price' => '10.00', 'line_total' => '20.00']), new PaymentLinkItem(['description' => 'Flete', 'quantity' => 1, 'unit_price' => '5.00', 'line_total' => '5.00'])]));
        $this->assertEquals('ORDER123', (new PayPalGateway)->createOrder($link)['id']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders') && $r->hasHeader('PayPal-Request-Id', 'create-PAY-TEST') && $r['intent'] === 'CAPTURE' && $r['purchase_units'][0]['amount']['value'] === '25.00' && $r['purchase_units'][0]['items'][0]['quantity'] === '2');
    }

    public function test_capture_is_server_side_with_stable_request_id(): void
    {
        $this->fake(['id' => 'ORDER123', 'status' => 'COMPLETED']);
        (new PayPalGateway)->capture('ORDER123', 'capture-PAY-TEST');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders/ORDER123/capture') && $r->hasHeader('PayPal-Request-Id', 'capture-PAY-TEST'));
    }

    public function test_signature_uses_official_verification_with_webhook_id_and_headers(): void
    {
        $this->fake(['verification_status' => 'SUCCESS']);
        $request = Request::create('/webhooks/paypal', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['id' => 'EVENT123']));
        foreach (['auth-algo' => 'SHA256withRSA', 'cert-url' => 'https://api.paypal.com/cert', 'transmission-id' => 'T123', 'transmission-sig' => 'signature', 'transmission-time' => '2026-10-14T00:00:00Z'] as $k => $v) {
            $request->headers->set('paypal-'.$k, $v);
        }
        $this->assertTrue((new PayPalGateway)->verifyWebhook($request));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/notifications/verify-webhook-signature') && $r['webhook_id'] === 'test-webhook' && $r['webhook_event']['id'] === 'EVENT123' && $r['transmission_sig'] === 'signature');
        $request->headers->remove('paypal-transmission-sig');
        $this->assertFalse((new PayPalGateway)->verifyWebhook($request));
    }

    public function test_live_is_explicitly_disabled(): void
    {
        config(['services.paypal.environment' => 'live']);
        $this->expectException(\RuntimeException::class);
        (new PayPalGateway)->queryOrder('ORDER123');
    }
}
