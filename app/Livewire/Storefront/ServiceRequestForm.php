<?php

namespace App\Livewire\Storefront;

use App\Http\Requests\Storefront\ServiceRequest;
use App\Services\Storefront\ServiceRequestService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ServiceRequestForm extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $location = '';

    public string $machine = '';

    public string $description = '';

    public $image;

    protected function rules(): array
    {
        return (new ServiceRequest)->rules();
    }

    public function submit(ServiceRequestService $requests): void
    {
        $data = $this->validate();
        $requests->create($data, $this->image);
        $this->reset();
        session()->flash('service-success', 'Solicitud registrada. Revisaremos la información y te contactaremos.');
    }

    public function render()
    {
        return view('livewire.storefront.service-request-form');
    }
}
