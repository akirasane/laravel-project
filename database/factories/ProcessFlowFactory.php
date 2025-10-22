<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessFlow>
 */
class ProcessFlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Flow',
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(80),
            'conditions' => [
                'platform_type' => $this->faker->optional()->randomElement(['shopee', 'lazada', 'shopify', 'tiktok']),
                'order_amount_min' => $this->faker->optional()->randomFloat(2, 0, 100),
                'order_amount_max' => $this->faker->optional()->randomFloat(2, 100, 1000),
            ],
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
