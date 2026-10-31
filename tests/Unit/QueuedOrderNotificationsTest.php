<?php

namespace Tests\Unit;

use App\Models\OrderStatus;
use App\Models\SaleOrder;
use App\Notifications\OrderPhaseUpdated;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;

class QueuedOrderNotificationsTest extends TestCase
{
    public function test_order_status_notification_runs_after_commit_on_notification_queue(): void
    {
        $notification = new OrderStatusUpdated(new SaleOrder);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame('notifications', $notification->queue);
    }

    public function test_phase_notification_runs_after_commit_on_notification_queue(): void
    {
        $notification = new OrderPhaseUpdated(
            new OrderStatus,
            null,
            'Entrega',
            'Entregado',
            'Pedido entregado'
        );

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame('notifications', $notification->queue);
    }
}
