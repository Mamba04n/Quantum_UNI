<?php

namespace App\Notifications;

use App\Models\SaleOrder;
use App\Support\OrderEmailPresentation as Presentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public SaleOrder $order)
    {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->statusCopy();
        $items = Presentation::items($this->order);
        $logo = Presentation::logo();
        $mail = (new MailMessage)
            ->subject("Actualización de pedido {$this->order->reference} - {$status['label']}")
            ->view(['emails.order-status-updated', 'emails.order-update-text'], [
                'order' => $this->order, 'orderItems' => $items,
                'statusLabel' => $status['label'], 'statusMessage' => $status['message'],
                'logoUrl' => $logo['src'] ?? null, 'portalUrl' => url('/mi-portal'),
                'currencyLabel' => Presentation::currency($this->order->currency),
                'heading' => 'Actualización de tu pedido', 'showTotals' => true,
                'phaseLabel' => null, 'isItemUpdate' => false, 'isPackingList' => false,
            ]);

        return Presentation::embed($mail, [$logo, ...array_column($items, 'image')]);
    }

    private function statusCopy(): array
    {
        return match ($this->order->status) {
            'cotizacion', 'cotización' => [
                'label' => 'Cotización',
                'message' => 'Se ha generado una nueva cotización para su revisión.',
            ],
            'solicitado_proveedor' => [
                'label' => 'Pedido a proveedor',
                'message' => 'El pedido fue procesado y las piezas fueron solicitadas al proveedor correspondiente.',
            ],
            'en_transito_aduanas' => [
                'label' => 'En tránsito de importación',
                'message' => 'Su pedido se encuentra en tránsito internacional y trámite de aduana.',
            ],
            'en_inventario_local' => [
                'label' => 'En bodega',
                'message' => 'Las piezas han ingresado a nuestras instalaciones y se encuentran listas para despacho.',
            ],
            'en_ruta' => [
                'label' => 'En ruta de entrega',
                'message' => 'Su pedido fue despachado por nuestro equipo de logística y se encuentra en ruta de entrega.',
            ],
            'entregado', 'delivered', 'entregado_total', 'cerrado' => [
                'label' => 'Entregado',
                'message' => 'El pedido fue entregado exitosamente. Agradecemos su preferencia.',
            ],
            'cancelado', 'cancelled', 'cancelado_total' => [
                'label' => 'Cancelado',
                'message' => 'Le informamos que su pedido fue cancelado. Para más información, puede comunicarse con nuestro equipo.',
            ],
            'generated' => [
                'label' => 'Orden generada',
                'message' => 'Su orden fue registrada y se encuentra en proceso de coordinación interna.',
            ],
            'pending_purchase' => [
                'label' => 'Pendiente de compra',
                'message' => 'Su orden fue registrada y contiene partidas pendientes de compra o ingreso de inventario.',
            ],
            'confirmed' => [
                'label' => 'Confirmado',
                'message' => 'Su pedido fue confirmado y se encuentra en proceso de gestión.',
            ],
            default => [
                'label' => 'En proceso',
                'message' => 'Su pedido se encuentra actualmente en procesamiento.',
            ],
        };
    }

    public function toArray(object $notifiable): array
    {
        return ['order_id' => $this->order->id, 'status' => $this->order->status];
    }
}
