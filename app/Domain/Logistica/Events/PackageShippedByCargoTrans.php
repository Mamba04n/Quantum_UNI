<?php

namespace App\Domain\Logistica\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackageShippedByCargoTrans
{
    use Dispatchable, SerializesModels;

    public string $eventId;
    public string $orderId;
    public string $shippingCost;
    public string $currency;
    public ?int $userId;
    public string $timestamp;
    public ?int $clientId;
    public ?int $clientMachineId;
    public ?int $productId;
    public ?int $quantity;

    public function __construct(
        string $eventId,
        string $orderId,
        string $shippingCost,
        string $currency,
        ?int $userId,
        string $timestamp,
        ?int $clientId,
        ?int $clientMachineId,
        ?int $productId,
        ?int $quantity
    ) {
        $this->eventId = $eventId;
        $this->orderId = $orderId;
        $this->shippingCost = $shippingCost;
        $this->currency = $currency;
        $this->userId = $userId;
        $this->timestamp = $timestamp;
        $this->clientId = $clientId;
        $this->clientMachineId = $clientMachineId;
        $this->productId = $productId;
        $this->quantity = $quantity;
    }
}
