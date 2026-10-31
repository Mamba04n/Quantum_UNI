<?php

namespace App\Notifications;

use App\Support\OrderEmailPresentation as Presentation;
use App\Models\OrderStatus;
use App\Models\OrderStatusDocument;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PackingListReady extends Notification
{
    public function __construct(
        private readonly OrderStatus $orderStatus,
        private readonly OrderStatusDocument $document,
        private readonly array $items
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->orderStatus->saleOrder;
        $logo = Presentation::logo();
        $items = collect($this->items)->map(function ($item) {
            $image = Presentation::productImage($item['image_path'] ?? $item['image_src'] ?? null);
            return [
                'description' => Presentation::text($item['description']),
                'short_description' => Presentation::summary($item['short_description'] ?? '', $item['description'], $item['brand'] ?? null),
                'part_number' => $item['part_number'] ?? null,
                'quantity' => (float) $item['quantity_dispatched'],
                'image_url' => $image['src'] ?? null, 'image' => $image,
            ];
        })->all();
        $mail = (new MailMessage)
            ->subject("Packing list {$this->orderStatus->order_code} - Pedido en ruta")
            ->view(['emails.order-status-updated', 'emails.order-update-text'], [
                'order' => $order, 'orderItems' => $items,
                'statusLabel' => 'Despacho registrado', 'statusMessage' => 'Hemos preparado el documento de los productos incluidos en este despacho. Revisa el detalle y las cantidades a continuación.',
                'logoUrl' => $logo['src'] ?? null, 'portalUrl' => url('/mi-portal'),
                'heading' => 'Tu packing list está disponible', 'showTotals' => false,
                'currencyLabel' => Presentation::currency($order?->currency),
                'phaseLabel' => 'Despacho', 'isItemUpdate' => false, 'isPackingList' => true,
            ]);

        if (! Storage::disk('private')->exists($this->document->file_path)) {
            throw new \RuntimeException('El packing list no está disponible para adjuntarlo al correo.');
        }
        if (Storage::disk('private')->exists($this->document->file_path)) {
            $mail->attach(Storage::disk('private')->path($this->document->file_path), [
                'as' => $this->document->file_name,
                'mime' => 'application/pdf',
            ]);
        }

        return Presentation::embed($mail, [$logo, ...array_column($items, 'image')]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_status_id' => $this->orderStatus->id,
            'document_id' => $this->document->id,
        ];
    }
}
