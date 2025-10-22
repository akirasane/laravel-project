<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['shopee', 'lazada', 'shopify', 'tiktok'];
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        $workflowStatuses = ['new', 'in_progress', 'completed', 'on_hold'];
        $syncStatuses = ['synced', 'pending', 'failed'];

        return [
            'platform_order_id' => $this->faker->unique()->numerify('ORD-########'),
            'platform_type' => $this->faker->randomElement($platforms),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->email(),
            'customer_phone' => $this->faker->phoneNumber(),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'THB', 'SGD']),
            'status' => $this->faker->randomElement($statuses),
            'workflow_status' => $this->faker->randomElement($workflowStatuses),
            'order_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'sync_status' => $this->faker->randomElement($syncStatuses),
            'raw_data' => [
                'original_platform_data' => $this->faker->words(10, true),
                'api_version' => $this->faker->randomElement(['v1', 'v2', 'v3']),
            ],
            'shipping_address' => $this->faker->address(),
            'billing_address' => $this->faker->address(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
