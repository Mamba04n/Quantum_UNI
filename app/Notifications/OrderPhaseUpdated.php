<?php

namespace App\Notifications;

use App\Models\OrderStatus;
use App\Models\OrderStatusEvent;
use App\Models\OrderStatusItem;
use App\Support\OrderEmailPresentation as Presentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPhaseUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly OrderStatus $orderStatus,
        private readonly ?OrderStatusItem $item,
        private readonly string $phaseLabel,
        private readonly string $statusLabel,
        private readonly string $message,
        public readonly ?int $orderStatusEventId = null,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function itemId(): ?int
    {
        return $this->item?->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->orderStatus->saleOrder;
        $itemLabel = $this->item?->part_name ?: $this->item?->part_number;
        $partNumber = $this->item?->part_number;

        $items = [];
        if ($this->item) {
            if ($this->item->exists) {
                $this->item->loadMissing(['product', 'saleOrderItem.product']);
            }
            $product = $this->item->product ?: $this->item->saleOrderItem?->product;
            $title = Presentation::text($this->item->part_name ?: $product?->name ?: $partNumber ?: 'Producto');
            $image = Presentation::productImage($product?->image_path);
            $items[] = ['description' => $title, 'short_description' => Presentation::summary($product?->description, $title, $product?->brand), 'part_number' => $partNumber, 'quantity' => (float) $this->item->quantity_ordered, 'image_url' => $image['src'] ?? null, 'image' => $image];
        } elseif ($order) {
            $items = Presentation::items($order);
        }
        $logo = Presentation::logo();
        $mail = (new MailMessage)
            ->subject("Actualización {$order?->reference} · ".($partNumber ?: $itemLabel ?: $this->phaseLabel))
            ->view(['emails.order-status-updated', 'emails.order-update-text'], [
                'order' => $order, 'orderItems' => $items, 'statusLabel' => $this->statusLabel,
                'statusMessage' => $this->message, 'logoUrl' => $logo['src'] ?? null,
                'portalUrl' => url('/mi-portal'), 'heading' => 'Avance de tu producto',
                'phaseLabel' => $this->phaseLabel, 'showTotals' => false,
                'isItemUpdate' => (bool) $this->item, 'isPackingList' => false,
                'currencyLabel' => Presentation::currency($order?->currency),
            ]);

        return Presentation::embed($mail, [$logo, ...array_column($items, 'image')]);
    }

    public function failed(\Throwable $exception): void
    {
        if (! $this->orderStatusEventId) {
            return;
        }

        OrderStatusEvent::whereKey($this->orderStatusEventId)->update([
            'customer_notification_status' => 'failed',
            'customer_notification_failed_at' => now(),
            'customer_notification_error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}
