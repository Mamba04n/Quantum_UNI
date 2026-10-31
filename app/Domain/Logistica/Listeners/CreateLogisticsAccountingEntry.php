<?php

namespace App\Domain\Logistica\Listeners;

use App\Domain\Logistica\Events\PackageShippedByCargoTrans;
use App\Models\JournalEntry;

class CreateLogisticsAccountingEntry
{
    /**
     * Handle the event.
     */
    public function handle(PackageShippedByCargoTrans $event): void
    {
        // Generar póliza contable de salida de inventario / gasto de envío
        $entry = JournalEntry::create([
            'event_uuid' => $event->eventId,
            'reference' => $event->orderId,
            'currency' => $event->currency,
            'user_id' => $event->userId,
            'client_id' => $event->clientId,
            'client_machine_id' => $event->clientMachineId,
            'posted_at' => $event->timestamp,
            'source' => 'LogisticsShipment',
            'description' => 'Cargo de envío por salida. Orden: ' . $event->orderId,
        ]);
    }
}
