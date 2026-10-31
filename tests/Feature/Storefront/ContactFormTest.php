<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\ContactForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_validates_required_fields(): void
    {
        Livewire::test(ContactForm::class)->call('submit')->assertHasErrors(['name', 'email', 'message']);
    }

    public function test_contact_form_saves_message(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'Ana López')
            ->set('email', 'ana@example.com')
            ->set('message', 'Necesito información sobre una pieza.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('storefront_contact_messages', ['email' => 'ana@example.com']);
    }
}
