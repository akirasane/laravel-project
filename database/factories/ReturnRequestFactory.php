<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $returnTypes = ['in_store', 'mail'];
        $reasonCodes = ['defective', 'wrong_item', 'not_as_described', 'changed_mind', 'damaged_shipping', 'other'];
        $statuses = ['requested', 'approved', 'rejected', 'in_transit', 'received', 'processed', 'completed'];
        
        $requestedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $status = $this->faker->randomElement($statuses);
        
        $approvedAt = null;
        $receivedAt = null;
        $processedAt = null;
        
        if (in_array($status, ['approved', 'in_transit', 'received', 'processed', 'completed'])) {
            $approvedAt = $this->faker->dateTimeBetween($requestedAt, 'now');
        }
        
        if (in_array($status, ['received', 'processed', 'completed'])) {
            $receivedAt = $this->faker->dateTimeBetween($approvedAt ?? $requestedAt, 'now');
        }
        
        if (in_array($status, ['processed', 'completed'])) {
            $processedAt = $this->faker->dateTimeBetween($receivedAt ?? $requestedAt, 'now');
        }

        return [
            'order_id' => \App\Models\Order::factory(),
            'return_authorization_number' => 'RET-' . $this->faker->unique()->numerify('########'),
            'return_type' => $this->faker->randomElement($returnTypes),
            'reason_code' => $this->faker->randomElement($reasonCodes),
            'reason_description' => $this->faker->optional()->sentence(),
            'status' => $status,
            'return_amount' => $this->faker->randomFloat(2, 10, 500),
            'items_to_return' => [
                ['item_id' => 1, 'quantity' => $this->faker->numberBetween(1, 3)],
                ['item_id' => 2, 'quantity' => $this->faker->numberBetween(1, 2)],
            ],
            'shipping_label_url' => $this->faker->optional()->url(),
            'tracking_number' => $this->faker->optional()->bothify('TRK-###-???-####'),
            'requested_at' => $requestedAt,
            'approved_at' => $approvedAt,
            'received_at' => $receivedAt,
            'processed_at' => $processedAt,
            'processed_by' => $processedAt ? $this->faker->randomElement([1, 2, 3]) : null,
            'processing_notes' => $this->faker->optional()->sentence(),
        ];
    }
}
