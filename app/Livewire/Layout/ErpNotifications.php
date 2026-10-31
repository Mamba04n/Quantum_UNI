<?php

namespace App\Livewire\Layout;

use App\Models\User;
use Livewire\Component;

class ErpNotifications extends Component
{
    public function render()
    {
        abort_unless(auth()->check() && ! auth()->user()->hasRole(User::ROLE_CLIENTE_TIENDA), 403);

        return view('livewire.layout.erp-notifications');
    }
}
