<?php

namespace App\Domain\Finanzas\PaymentLinks;

use App\Domain\Finanzas\ValueObjects\DecimalMoney;
use App\Models\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalGateway implements PaymentGateway
{
    private function endpoint(): string
    {
        // This first release intentionally cannot use live credentials/endpoints.
        if (config('services.paypal.environment') !== 'sandbox' || ! config('services.paypal.client_id') || ! config('services.paypal.client_secret')) {
            throw new RuntimeException('PayPal Sandbox no está configurado.');
        }

        return 'https://api-m.sandbox.paypal.com';
    }

    private function client()
    {
        $base = $this->endpoint();
        $r = Http::timeout(20)->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))->asForm()->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new RuntimeException('No se pudo conectar con PayPal.');
        }

        return Http::baseUrl($base)->timeout(25)->connectTimeout(10)->withToken($r->json('access_token'))->acceptJson();
    }

    private function result($response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('No se pudo completar la operación con PayPal.');
        }

        return $response->json();
    }

    public function createOrder(PaymentLink $link): array
    {
        $link->loadMissing('items');
        $total = '0.00';
        foreach ($link->items as $i) {
            $total = DecimalMoney::add($total, DecimalMoney::convertToBase($i->unit_price, (string) $i->quantity));
        }
        if ($total !== $link->total || $link->currency !== 'USD') {
            throw new RuntimeException('El cobro requiere revisión.');
        }

        return $this->result($this->client()->withHeaders(['PayPal-Request-Id' => 'create-'.$link->code])->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $link->code, 'custom_id' => $link->code, 'invoice_id' => $link->code,
                'amount' => ['currency_code' => 'USD', 'value' => $total, 'breakdown' => ['item_total' => ['currency_code' => 'USD', 'value' => $total]]],
                'items' => $link->items->map(fn ($i) => ['name' => mb_substr($i->description, 0, 127), 'quantity' => (string) $i->quantity, 'unit_amount' => ['currency_code' => 'USD', 'value' => $i->unit_price]])->all(),
            ]],
            'payment_source' => ['paypal' => ['experience_context' => ['shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW', 'return_url' => $link->publicUrl(), 'cancel_url' => $link->publicUrl()]]],
        ]));
    }

    public function capture(string $orderId, string $requestId): array
    {
        return $this->result($this->client()->withHeaders(['PayPal-Request-Id' => $requestId])->withBody('{}', 'application/json')->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture'));
    }

    public function queryOrder(string $orderId): array
    {
        return $this->result($this->client()->get('/v2/checkout/orders/'.rawurlencode($orderId)));
    }

    public function verifyWebhook(Request $request): bool
    {
        if (! config('services.paypal.webhook_id')) {
            return false;
        }
        $data = ['webhook_id' => config('services.paypal.webhook_id'), 'webhook_event' => $request->json()->all()];
        foreach (['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'] as $field) {
            $data[$field] = $request->header('paypal-'.str_replace('_', '-', $field));
            if (! $data[$field]) {
                return false;
            }
        }

        return ($this->result($this->client()->post('/v1/notifications/verify-webhook-signature', $data))['verification_status'] ?? '') === 'SUCCESS';
    }
}
