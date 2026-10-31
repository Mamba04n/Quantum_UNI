<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuotationRequest>
 */
class QuotationRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TEST-REQ-' . $this->faker->unique()->numberBetween(1000, 9999),
            'status' => \App\Models\QuotationRequest::STATUS_RECEIVED,
            'commercial_phase' => 'customer_request',
            'date' => now(),
            'user_id' => \App\Models\User::factory(),
            'quote_to_name' => $this->faker->company(),
        ];
    }
}
