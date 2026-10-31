<?php

namespace Tests\Feature;

use App\Domain\Pedidos\OrderStatus\Listeners\TrackOrderPhaseNotificationSent;
use App\Domain\Pedidos\OrderStatus\Services\CustomerOrderProgressNotifier;
use App\Models\OrderStatus;
use App\Models\OrderStatusEvent;
use App\Models\OrderStatusItem;
use App\Models\SaleOrder;
use App\Notifications\OrderPhaseUpdated;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerOrderProgressNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Notification::fake();
    }

    public function test_each_product_transition_notifies_its_exact_item(): void
    {
        [$order, $status, $first, $second] = $this->orderWithTwoItems();
        $firstEvent = $this->eventFor($status, $first, 'confirmacion_proveedor', 'esperando_confirmacion_proveedor', 'confirmado_proveedor');
        $secondEvent = $this->eventFor($status, $second, 'confirmacion_proveedor', 'esperando_confirmacion_proveedor', 'confirmado_proveedor');
        $notifier = app(CustomerOrderProgressNotifier::class);

        $notifier->notifyEvent($firstEvent->id);
        $notifier->notifyEvent($secondEvent->id);

        Notification::assertSentToTimes($order, OrderPhaseUpdated::class, 2);
        Notification::assertSentTo($order, OrderPhaseUpdated::class, fn (OrderPhaseUpdated $notification) => $notification->itemId() === $first->id);
        Notification::assertSentTo($order, OrderPhaseUpdated::class, fn (OrderPhaseUpdated $notification) => $notification->itemId() === $second->id);
        $this->assertSame('queued', $firstEvent->fresh()->customer_notification_status);
        $this->assertSame('queued', $secondEvent->fresh()->customer_notification_status);
    }

    public function test_internal_duplicate_and_aggregate_events_do_not_email_the_whole_order(): void
    {
        [$order, $status, $first] = $this->orderWithTwoItems();
        $receipt = $this->eventFor($status, $first, 'bodega', 'en_aduana', 'available_for_dispatch');
        $kardex = $this->eventFor($status, $first, 'bodega', 'en_aduana', 'available_for_dispatch', 'kardex.entry_created');
        $aggregate = OrderStatusEvent::create([
            'order_status_id' => $status->id,
            'event_type' => 'shipment.batch_created',
            'phase' => 'transito',
            'old_status' => 'confirmado_proveedor',
            'new_status' => 'en_transito',
            'description' => 'Embarque creado.',
        ]);
        $notifier = app(CustomerOrderProgressNotifier::class);

        $notifier->notifyEvent($receipt->id);
        $notifier->notifyEvent($kardex->id);
        $notifier->notifyEvent($aggregate->id);

        Notification::assertSentToTimes($order, OrderPhaseUpdated::class, 1);
        $this->assertSame('skipped_duplicate', $kardex->fresh()->customer_notification_status);
        $this->assertSame('skipped_aggregate', $aggregate->fresh()->customer_notification_status);
    }

    public function test_successful_mail_event_is_recorded_for_audit(): void
    {
        [$order, $status, $first] = $this->orderWithTwoItems();
        $statusEvent = $this->eventFor($status, $first, 'entregado', 'en_ruta_cliente', 'entregado_total');
        $notification = new OrderPhaseUpdated($status, $first, 'Entrega', 'Entregado', 'Partida entregada.', $statusEvent->id);

        app(TrackOrderPhaseNotificationSent::class)->handle(
            new NotificationSent($order, $notification, 'mail')
        );

        $statusEvent->refresh();
        $this->assertSame('sent', $statusEvent->customer_notification_status);
        $this->assertNotNull($statusEvent->customer_notification_sent_at);
    }

    private function orderWithTwoItems(): array
    {
        $order = SaleOrder::create([
            'reference' => 'OC-NOTIFY-'.str()->random(5),
            'date' => now()->toDateString(),
            'client_name' => 'Cliente prueba',
            'client_email' => 'cliente@example.com',
            'payment_type' => 'Contado',
            'status' => 'confirmed',
            'currency' => 'USD',
        ]);
        $status = OrderStatus::create(['sale_order_id' => $order->id]);
        $first = $this->item($status, 'Filtro de aceite', 'LF17483');
        $second = $this->item($status, 'Filtro hidráulico', 'HF6555');

        return [$order, $status, $first, $second];
    }

    private function item(OrderStatus $status, string $name, string $partNumber): OrderStatusItem
    {
        return $status->items()->create([
            'part_name' => $name,
            'part_number' => $partNumber,
            'quantity_ordered' => 1,
            'status' => 'esperando_confirmacion_proveedor',
        ]);
    }

    private function eventFor(
        OrderStatus $status,
        OrderStatusItem $item,
        string $phase,
        string $oldStatus,
        string $newStatus,
        string $eventType = 'supplier.item_confirmed',
    ): OrderStatusEvent {
        return OrderStatusEvent::create([
            'order_status_id' => $status->id,
            'order_status_item_id' => $item->id,
            'event_type' => $eventType,
            'phase' => $phase,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'description' => "Avance de {$item->part_number}.",
        ]);
    }
}
