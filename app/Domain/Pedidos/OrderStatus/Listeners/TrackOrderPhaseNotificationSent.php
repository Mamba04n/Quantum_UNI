<?php

namespace App\Domain\Pedidos\OrderStatus\Listeners;

use App\Models\OrderStatusEvent;
use App\Notifications\OrderPhaseUpdated;
use Illuminate\Notifications\Events\NotificationSent;

class TrackOrderPhaseNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail'
            || ! $event->notification instanceof OrderPhaseUpdated
            || ! $event->notification->orderStatusEventId) {
            return;
        }

        OrderStatusEvent::whereKey($event->notification->orderStatusEventId)->update([
            'customer_notification_status' => 'sent',
            'customer_notification_sent_at' => now(),
            'customer_notification_failed_at' => null,
            'customer_notification_error' => null,
        ]);
    }
}
