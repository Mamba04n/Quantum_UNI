<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\PaymentLinks\PaymentGateway;
use App\Domain\Finanzas\PaymentLinks\PaymentLinkService;
use App\Models\OrderTracker;
use App\Models\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicPaymentController extends Controller
{
    private function link(string $token): PaymentLink
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);

        return PaymentLink::where('public_token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token) || ! ($link = PaymentLink::where('public_token', $token)->first())) {
            return response()->view('public-commerce.unavailable', [], 404)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $link->load('items');
        $tracking = $link->status === 'paid' && $link->sale_order_id ? OrderTracker::where('public_sale_order_id', $link->sale_order_id)->value('public_tracking_code') : null;

        return response()->view('public-commerce.payment', ['link' => $link, 'tracking' => $tracking])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function checkout(string $token, PaymentLinkService $service)
    {
        $link = $this->link($token);
        try {
            return response()->json(['id' => $service->checkout($link)]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            return response()->json(['message' => 'No se pudo iniciar PayPal. Intenta nuevamente o contacta con Quantum.'], 503);
        }
    }

    public function capture(string $token, Request $request, PaymentLinkService $service)
    {
        $data = $request->validate(['order_id' => 'required|string|max:64|regex:/^[A-Z0-9]+$/D']);
        $link = $this->link($token);
        try {
            $paid = $service->capture($link, $data['order_id']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            return response()->json(['message' => 'No se pudo confirmar el pago. Consulta el estado del enlace antes de volver a pagar.'], 503);
        }

        return response()->json(['paid' => $paid], $paid ? 200 : 409);
    }

    public function webhook(Request $request, PaymentGateway $gateway, PaymentLinkService $service)
    {
        abort_if(strlen($request->getContent()) > 131072, 413);
        abort_unless($gateway->verifyWebhook($request), 400);
        $event = $request->validate(['id' => 'required|string|max:128', 'event_type' => 'required|string|max:128', 'resource' => 'required|array']);
        $hash = hash('sha256', $request->getContent());
        $orderId = data_get($event, 'resource.supplementary_data.related_ids.order_id');
        $link = $orderId ? PaymentLink::where('provider_order_id', $orderId)->first() : null;
        // Network lookup outside financial transaction, never trust browser/webhook amount alone.
        $order = $link && $event['event_type'] === 'PAYMENT.CAPTURE.COMPLETED' ? $gateway->queryOrder($orderId) : null;
        DB::transaction(function () use ($event, $hash, $link, $order, $service) {
            DB::table('payment_provider_events')->insertOrIgnore(['provider' => 'paypal', 'event_id' => $event['id'], 'event_type' => $event['event_type'], 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('payment_provider_events')->where('provider', 'paypal')->where('event_id', $event['id'])->lockForUpdate()->first();
            if ($row->processed_at) {
                return;
            }
            if ($row->payload_hash !== $hash) {
                abort(409);
            }
            if ($link && $order) {
                $service->confirm($link, $order);
            }
            if ($event['event_type'] === 'PAYMENT.CAPTURE.REFUNDED') {
                $captureId = data_get($event, 'resource.supplementary_data.related_ids.capture_id');
                if ($captureId) {
                    PaymentLink::where('provider_capture_id', $captureId)->update(['review_reason' => 'refund_requires_finance_review', 'provider_status' => 'REFUNDED']);
                }
            }
            DB::table('payment_provider_events')->where('id', $row->id)->update(['processed_at' => now()]);
        }, 3);

        return response()->json(['received' => true]);
    }
}
