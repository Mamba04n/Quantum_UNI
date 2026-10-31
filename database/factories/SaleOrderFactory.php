<?php

namespace Database\Factories;

use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleOrderFactory extends Factory
{
    protected $model = SaleOrder::class;

    public function definition(): array
    {
        return [
            'reference' => $this->faker->unique()->bothify('OC#####'),
            'date' => $this->faker->date(),
            'client_name' => $this->faker->company(),
            'client_ruc' => $this->faker->optional()->numerify('###########'),
            'shipping_address' => $this->faker->address(),
            'provider_name' => $this->faker->company(),
            'provider_ruc' => $this->faker->optional()->numerify('###########'),
            'payment_type' => $this->faker->randomElement(['Contado', 'Crédito']),
            'status' => $this->faker->randomElement(['draft', 'sent', 'confirmed', 'cancelled']),
            'subtotal' => $this->faker->randomFloat(2, 100, 10000),
            'tax_percent' => 15.00,
            'tax_amount' => $this->faker->randomFloat(2, 10, 1500),
            'total_amount' => $this->faker->randomFloat(2, 110, 11500),
            'currency' => 'USD',
            'terms_and_conditions' => $this->faker->sentence(),
        ];
    }
}
