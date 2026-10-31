<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactInquiryReceived extends Notification
{
    public function __construct(public int $inquiryId, public array $inquiry)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->mailer('contact')
            ->from(config('mail.mailers.contact.username'), 'Quantum Soluciones')
            ->subject('Nueva consulta web #'.$this->inquiryId)
            ->replyTo($this->inquiry['email'], $this->inquiry['name'])
            ->view('emails.contact-inquiry', ['inquiry' => $this->inquiry, 'inquiryId' => $this->inquiryId]);
    }
}
