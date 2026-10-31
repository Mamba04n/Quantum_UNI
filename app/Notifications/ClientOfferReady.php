<?php

namespace App\Notifications;

use App\Models\QuotationRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ClientOfferReady extends Notification
{
    public function __construct(
        private readonly QuotationRequest $quotation
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Oferta Quantum Soluciones '.$this->quotation->reference)
            ->greeting('Estimado cliente,')
            ->line('Adjuntamos la oferta comercial solicitada.')
            ->line('Por favor revise el documento PDF y responda indicando si acepta la oferta, requiere cambios o necesita asistencia adicional.')
            ->action('Ingresar al portal cliente', url('/mi-portal'))
            ->line('Gracias por confiar en Quantum Soluciones.');

        if ($this->quotation->client_offer_pdf_path && Storage::disk('private')->exists($this->quotation->client_offer_pdf_path)) {
            $mail->attach(Storage::disk('private')->path($this->quotation->client_offer_pdf_path), [
                'as' => 'Oferta_'.$this->quotation->reference.'.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'quotation_request_id' => $this->quotation->id,
            'reference' => $this->quotation->reference,
        ];
    }
}
