<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ContactInquiryReceived;

class ContactService
{
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $id = DB::table('storefront_contact_messages')->insertGetId([
            'nombre' => $data['name'],
            'email' => $data['email'],
            'telefono' => $data['phone'] ?? null,
            'mensaje' => $data['message'],
            'fecha' => now(),
            'leido' => false,
            ]);

            try {
                Notification::route('mail', config('mail.contact_recipient'))
                    ->notify(new ContactInquiryReceived($id, $data));
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $exception) {
                report($exception);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'message' => 'No pudimos enviar la consulta. Inténtalo nuevamente en unos minutos.',
                ]);
            }

            return $id;
        });
    }
}
