<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'type' => $this->faker->randomElement(['pieza', 'servicio']),
            'piece_rotation_type' => $this->faker->randomElement(['alta_rotacion', 'baja_rotacion', 'mantenimiento_rutinario']),
            'technical_category' => $this->faker->randomElement(['electrico', 'mecanico', 'hidraulico', 'otro']),
            'sales_price' => $this->faker->randomFloat(2, 10, 1000),
            'cost' => $this->faker->randomFloat(2, 5, 500),
            'total_cost' => 0,
            'stock_on_hand' => $this->faker->numberBetween(0, 100),
            'active' => true,
        ];
    }
}
