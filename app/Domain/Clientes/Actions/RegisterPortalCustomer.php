<?php

namespace App\Domain\Clientes\Actions;

use App\Domain\Clientes\Services\CustomerService;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use LogicException;

class RegisterPortalCustomer
{
    public function __construct(private readonly CustomerService $customers) {}

    public function execute(array $data): User
    {
        $customer = $this->customers->registerPortalCustomer($data);
        $user = $customer->user;

        if (! $user) {
            throw new LogicException('El registro de portal no produjo un usuario enlazado.');
        }

        event(new Registered($user));

        return $user;
    }
}
