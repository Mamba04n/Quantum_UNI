<?php

namespace App\Domain\Clientes\Support\Services;

use App\Domain\Pedidos\Tracking\Services\OrderTrackerService;
use App\Models\ActivityLog;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketNote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function createTicket(array $data, User $customer, ?UploadedFile $attachment = null): Ticket
    {
        return DB::transaction(function () use ($data, $customer, $attachment) {
            $code = $this->generateCode();
            $attachmentPath = $attachment ? $attachment->store('tickets', 'private') : null;

            $ticket = Ticket::create([
                'code' => $code,
                'customer_id' => $customer->id,
                'type' => $data['type'],
                'subject' => trim($data['subject']),
                'description' => trim($data['description']),
                'priority' => $data['priority'],
                'status' => Ticket::STATUS_OPEN,
                'client_machine_id' => ($data['client_machine_id'] ?? null) ?: null,
                'related_product_id' => ($data['related_product_id'] ?? null) ?: null,
                'related_sku' => ($data['related_sku'] ?? null) ?: null,
                'related_stock_move_id' => ($data['related_stock_move_id'] ?? null) ?: null,
                'attachment_path' => $attachmentPath,
                'last_message_at' => now(),
                'last_customer_message_at' => now(),
                'machinery' => $data['machinery'] ?? null,
                'machine_brand' => $data['machine_brand'] ?? null,
                'machine_model' => $data['machine_model'] ?? null,
                'equipment_serial' => $data['equipment_serial'] ?? null,
                'part_code' => $data['part_code'] ?? null,
                'request_quantity' => $data['request_quantity'] ?? null,
                'request_urgency' => $data['request_urgency'] ?? null,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $customer->id,
                'sender_type' => 'customer',
                'message' => trim($data['description']),
                'attachment_path' => $attachmentPath,
            ]);

            if ($ticket->type === Ticket::TYPE_PURCHASE) {
                $quotation = $this->createQuotationFromTicket($ticket, $customer);
                $ticket->update(['quotation_request_id' => $quotation->id]);
            }

            ActivityLog::log('crm', 'ticket_created', "Ticket {$ticket->code} creado por {$customer->name}", [
                'ticket_id' => $ticket->id,
                'type' => $ticket->type,
                'priority' => $ticket->priority,
            ]);

            return $ticket->fresh(['customer', 'product', 'stockMove', 'quotationRequest']);
        });
    }

    public function addMessage(Ticket $ticket, User $sender, string $message, string $senderType, ?UploadedFile $attachment = null): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $sender, $message, $senderType, $attachment) {
            $attachmentPath = $attachment ? $attachment->store('ticket-messages', 'private') : null;

            $ticketMessage = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $sender->id,
                'sender_type' => $senderType,
                'message' => trim($message),
                'attachment_path' => $attachmentPath,
            ]);

            $ticket->update([
                'status' => $senderType === 'customer' ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_WAITING_CUSTOMER,
                'last_message_at' => now(),
                'last_customer_message_at' => $senderType === 'customer' ? now() : $ticket->last_customer_message_at,
                'last_admin_message_at' => $senderType === 'admin' ? now() : $ticket->last_admin_message_at,
            ]);

            ActivityLog::log('crm', 'ticket_message', "Nuevo mensaje en ticket {$ticket->code}", [
                'ticket_id' => $ticket->id,
                'ticket_message_id' => $ticketMessage->id,
                'sender_type' => $senderType,
            ]);

            return $ticketMessage->fresh('sender');
        });
    }

    public function addInternalNote(Ticket $ticket, User $admin, string $note): TicketNote
    {
        return DB::transaction(function () use ($ticket, $admin, $note) {
            $ticket->loadMissing('quotationRequest');

            if ($ticket->quotationRequest) {
                $trackerService = app(OrderTrackerService::class);
                $tracker = $trackerService->trackerForQuotation($ticket->quotationRequest);

                $activity = $trackerService->recordActivity(
                    $tracker,
                    'ticket_internal_note',
                    "Nota interna agregada desde ticket {$ticket->code}",
                    'comentario',
                    'tickets',
                    $ticket->id,
                    null,
                    null,
                    $admin
                );

                $trackerService->addComment($tracker, $note, 'internal', $activity, $admin);

                ActivityLog::log('crm', 'ticket_note_synced_to_tracker', "Nota interna del ticket {$ticket->code} guardada en tracker {$tracker->tracking_code}", [
                    'ticket_id' => $ticket->id,
                    'tracker_id' => $tracker->id,
                    'tracking_code' => $tracker->tracking_code,
                ]);

                return new TicketNote([
                    'ticket_id' => $ticket->id,
                    'admin_id' => $admin->id,
                    'note' => trim($note),
                ]);
            }

            $ticketNote = TicketNote::create([
                'ticket_id' => $ticket->id,
                'admin_id' => $admin->id,
                'note' => trim($note),
            ]);

            ActivityLog::log('crm', 'ticket_note', "Nota interna agregada en ticket {$ticket->code}", [
                'ticket_id' => $ticket->id,
                'ticket_note_id' => $ticketNote->id,
            ]);

            return $ticketNote->fresh('admin');
        });
    }

    public function markMessagesAsRead(Ticket $ticket, string $readerType): void
    {
        $senderType = $readerType === 'admin' ? 'customer' : 'admin';

        $ticket->messages()
            ->where('sender_type', $senderType)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function updateTicketState(Ticket $ticket, array $payload): void
    {
        $ticket->update($payload);

        ActivityLog::log('crm', 'ticket_updated', "Ticket {$ticket->code} actualizado", [
            'ticket_id' => $ticket->id,
            'changes' => $payload,
        ]);
    }

    private function generateCode(): string
    {
        return DB::transaction(function () {
            $year = (int) now()->format('Y');

            DB::table('ticket_sequences')->insertOrIgnore([
                'year' => $year,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('ticket_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;

            DB::table('ticket_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_number' => $number + 1,
                    'updated_at' => now(),
                ]);

            return sprintf('TKT-%d-%04d', $year, $number);
        });
    }

    private function createQuotationFromTicket(Ticket $ticket, User $customer): QuotationRequest
    {
        $reference = 'QR-TKT-'.$ticket->id;

        $quotation = QuotationRequest::create([
            'reference' => $reference,
            'date' => now()->toDateString(),
            'quote_to_name' => $customer->name,
            'quote_to_email' => $customer->email,
            'requested_by' => $customer->name,
            'department' => 'tickets',
            'source_channel' => 'portal_cliente',
            'machinery' => $ticket->machinery,
            'machine_brand' => $ticket->machine_brand,
            'machine_model' => $ticket->machine_model,
            'equipment_serial' => $ticket->equipment_serial,
            'part_code' => $ticket->part_code,
            'request_quantity' => $ticket->request_quantity ?: 1,
            'request_urgency' => $ticket->request_urgency,
            'subtotal' => 0,
            'total_amount' => 0,
            'status' => QuotationRequest::STATUS_RECEIVED,
            'notes' => "Generada automáticamente desde ticket {$ticket->code}",
            'user_id' => $customer->id,
            'client_machine_id' => $ticket->client_machine_id,
        ]);

        QuotationRequestItem::create([
            'quotation_request_id' => $quotation->id,
            'part_number' => $ticket->part_code ?: $ticket->related_sku,
            'description' => $ticket->subject,
            'quantity' => $ticket->request_quantity ?: 1,
            'unit_price' => 0,
            'line_total' => 0,
            'internal_request_ref' => $ticket->code,
            'brand' => $ticket->machine_brand,
        ]);

        ActivityLog::log('ventas', 'ticket_purchase_request', "Solicitud de compra creada desde ticket {$ticket->code}", [
            'ticket_id' => $ticket->id,
            'quotation_request_id' => $quotation->id,
        ]);

        return $quotation;
    }
}
