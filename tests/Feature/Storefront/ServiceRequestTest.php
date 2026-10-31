<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\ServiceRequestForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_is_saved(): void
    {
        Livewire::test(ServiceRequestForm::class)
            ->set('name', 'Carlos Pérez')
            ->set('email', 'carlos@example.com')
            ->set('description', 'La máquina presenta pérdida de presión hidráulica.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('storefront_service_requests', ['email' => 'carlos@example.com']);
    }
}
