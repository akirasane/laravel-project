<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 5, 200);
        $totalPrice = $quantity * $unitPrice;

        return [
            'product_id' => $this->faker->numerify('PROD-####'),
            'product_name' => $this->faker->words(3, true),
            'product_sku' => $this->faker->optional()->bothify('SKU-###-???'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'product_image_url' => $this->faker->optional()->imageUrl(300, 300, 'products'),
            'product_attributes' => [
                'size' => $this->faker->optional()->randomElement(['S', 'M', 'L', 'XL']),
                'color' => $this->faker->optional()->colorName(),
                'material' => $this->faker->optional()->word(),
            ],
        ];
    }
}
