<?php

namespace App\Notifications;

use App\Models\QuotationRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebQuotationRequestNotification extends Notification
{
    public function __construct(public QuotationRequest $quotation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $itemsCount = $this->quotation->items()->count();

        return (new MailMessage)
            ->subject('Nueva solicitud de cotización web: '.$this->quotation->reference)
            ->replyTo($this->quotation->quote_to_email, $this->quotation->quote_to_name)
            ->view('emails.web-quotation-received', [
                'quotation' => $this->quotation,
                'itemsCount' => $itemsCount,
            ]);
    }
}
