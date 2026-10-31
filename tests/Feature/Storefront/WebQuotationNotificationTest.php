<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Notifications\WebQuotationRequestNotification;
use App\Services\Storefront\CartService;
use App\Services\Storefront\QuoteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebQuotationNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_web_quotation_creation_triggers_notification_and_appears_in_erp(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'name' => 'Bomba Hidráulica 320D',
            'sku' => 'HYD-320D',
            'sales_price' => 1250.00,
            'active' => true,
        ]);

        $cart = app(CartService::class);
        $cart->add($product, 2);

        $quoteService = app(QuoteService::class);
        $result = $quoteService->create([
            'name' => 'Constructora Managua',
            'email' => 'compras@constructora.com',
            'phone' => '+505 8888 9999',
            'ruc' => 'J0310000000001',
            'contact_name' => 'Carlos Morales',
            'notes' => 'Urgente para proyecto Carretera a Masaya',
        ]);

        $this->assertDatabaseHas('quotation_requests', [
            'reference' => $result['code'],
            'quote_to_name' => 'Constructora Managua',
            'source_channel' => 'web',
            'status' => QuotationRequest::STATUS_RECEIVED,
        ]);

        $quote = QuotationRequest::where('reference', $result['code'])->first();

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            WebQuotationRequestNotification::class,
            function ($notification) use ($quote) {
                return $notification->quotation->id === $quote->id;
            }
        );

        // Employee entering ERP sees the notification in /inicio
        $adminRole = \App\Models\Role::firstOrCreate(['slug' => User::ROLE_ADMIN], ['name' => 'Administrador']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $response = $this->actingAs($admin)->get('/inicio');
        $response->assertStatus(200);
        $response->assertSee('1 nueva solicitud de cotización recibida desde la web');
        $response->assertSee('Cotizaciones Web Recibidas');
        $response->assertSee($result['code']);
    }
}
