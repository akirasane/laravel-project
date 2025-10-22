<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        $changeTypes = ['user', 'system', 'api'];
        
        $previousStatus = $this->faker->randomElement($statuses);
        $newStatus = $this->faker->randomElement(array_diff($statuses, [$previousStatus]));

        return [
            'order_id' => \App\Models\Order::factory(),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'changed_by_type' => $this->faker->randomElement($changeTypes),
            'changed_by_id' => $this->faker->optional()->randomElement([1, 2, 3]), // Assuming some user IDs exist
            'reason' => $this->faker->optional()->sentence(),
            'metadata' => [
                'ip_address' => $this->faker->optional()->ipv4(),
                'user_agent' => $this->faker->optional()->userAgent(),
                'source' => $this->faker->randomElement(['web', 'api', 'webhook']),
            ],
            'is_reversible' => $this->faker->boolean(70),
            'reversed_at' => null, // Will be set when status is actually reversed
        ];
    }

    /**
     * Indicate that the status change has been reversed.
     */
    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => [
            'reversed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'is_reversible' => false,
        ]);
    }
}