<?php

namespace Database\Factories;

use App\Models\LogisticsShipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogisticsShipmentFactory extends Factory
{
    protected $model = LogisticsShipment::class;

    public function definition(): array
    {
        return [
            'order_id' => $this->faker->unique()->bothify('LS#####'),
            'shipping_cost' => $this->faker->randomFloat(2, 50, 2000),
            'currency' => 'USD',
            'user_id' => 1, // Asume que existe un usuario con ID 1
            'client_id' => 1, // Asume que existe un cliente con ID 1
            'machine_id' => null,
            'shipped_at' => $this->faker->dateTimeThisYear(),
        ];
    }
}
