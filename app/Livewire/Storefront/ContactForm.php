<?php

namespace App\Livewire\Storefront;

use App\Http\Requests\Storefront\ContactRequest;
use App\Services\Storefront\ContactService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    protected function rules(): array
    {
        return (new ContactRequest)->rules();
    }

    public function submit(ContactService $contacts): void
    {
        $contacts->create($this->validate());
        $this->reset();
        session()->flash('success', 'Mensaje recibido. Te contactaremos.');
    }

    public function render()
    {
        return view('storefront.contact.index')->title('Contacto | Quantum Soluciones');
    }
}
