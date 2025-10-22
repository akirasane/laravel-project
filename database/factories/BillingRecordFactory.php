<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingRecord>
 */
class BillingRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $billingMethods = ['pos_api', 'manual'];
        $paymentStatuses = ['pending', 'paid', 'partially_paid', 'refunded', 'cancelled'];
        $paymentMethods = ['cash', 'card', 'bank_transfer', 'digital_wallet', 'other'];
        
        $subtotal = $this->faker->randomFloat(2, 50, 800);
        $taxAmount = $subtotal * 0.1; // 10% tax
        $discountAmount = $this->faker->randomFloat(2, 0, $subtotal * 0.2); // up to 20% discount
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        
        $billedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $paymentStatus = $this->faker->randomElement($paymentStatuses);
        $paidAt = $paymentStatus === 'paid' ? $this->faker->dateTimeBetween($billedAt, 'now') : null;

        return [
            'order_id' => \App\Models\Order::factory(),
            'invoice_number' => 'INV-' . $this->faker->unique()->numerify('########'),
            'billing_method' => $this->faker->randomElement($billingMethods),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'THB', 'SGD']),
            'payment_status' => $paymentStatus,
            'payment_method' => $this->faker->optional()->randomElement($paymentMethods),
            'pos_transaction_id' => $this->faker->optional()->bothify('POS-###-???-####'),
            'pos_response_data' => [
                'transaction_id' => $this->faker->uuid(),
                'response_code' => $this->faker->randomElement(['00', '01', '02']),
                'response_message' => $this->faker->randomElement(['Success', 'Declined', 'Error']),
            ],
            'billed_at' => $billedAt,
            'paid_at' => $paidAt,
            'created_by' => \App\Models\User::factory(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
