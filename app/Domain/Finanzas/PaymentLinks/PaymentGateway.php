<?php

namespace App\Domain\Finanzas\PaymentLinks;

use App\Models\PaymentLink;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createOrder(PaymentLink $link): array;

    public function capture(string $orderId, string $requestId): array;

    public function queryOrder(string $orderId): array;

    public function verifyWebhook(Request $request): bool;
}
