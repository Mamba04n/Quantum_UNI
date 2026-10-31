<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_customer_downloads_own_attachment_as_attachment(): void
    {
        Storage::fake('private');
        [$owner, $message] = $this->ticketMessageForCustomer('private-proof');

        $response = $this->actingAs($owner)
            ->get(route('private.ticket-message-attachment', [
                'message' => $message,
                'download' => 0,
            ]));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('private-proof', $response->streamedContent());
    }

    public function test_customer_cannot_download_another_customers_attachment(): void
    {
        Storage::fake('private');
        [, $message] = $this->ticketMessageForCustomer('owner-only');

        $this->actingAs($this->customer())
            ->get(route('private.ticket-message-attachment', $message))
            ->assertForbidden();
    }

    public function test_employee_without_resource_permission_receives_forbidden(): void
    {
        Storage::fake('private');
        [, $message] = $this->ticketMessageForCustomer('restricted');

        $this->actingAs(User::factory()->create())
            ->get(route('private.ticket-message-attachment', $message))
            ->assertForbidden();
    }

    public function test_missing_resource_returns_not_found(): void
    {
        $this->actingAs($this->customer())
            ->get(route('private.ticket-message-attachment', ['message' => 999999]))
            ->assertNotFound();
    }

    public function test_private_file_is_not_available_from_storage_url(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        [, $message] = $this->ticketMessageForCustomer('not-public');

        Storage::disk('private')->assertExists($message->attachment_path);
        Storage::disk('public')->assertMissing($message->attachment_path);
        $this->get('/storage/'.$message->attachment_path)->assertNotFound();
    }

    /** @return array{User, TicketMessage} */
    private function ticketMessageForCustomer(string $contents): array
    {
        $customer = $this->customer();
        $path = 'ticket-messages/proof-'.$customer->id.'.pdf';
        Storage::disk('private')->put($path, $contents);

        $ticket = Ticket::create([
            'code' => 'TKT-SECURITY-'.$customer->id,
            'customer_id' => $customer->id,
            'type' => 'soporte',
            'subject' => 'Adjunto privado',
            'description' => 'Prueba de autorizacion.',
            'priority' => 'media',
            'status' => Ticket::STATUS_OPEN,
            'attachment_path' => $path,
        ]);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $customer->id,
            'sender_type' => 'customer',
            'message' => 'Archivo',
            'attachment_path' => $path,
        ]);

        return [$customer, $message];
    }

    private function customer(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => User::ROLE_CLIENTE_TIENDA],
            ['name' => 'Cliente tienda']
        );
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}
