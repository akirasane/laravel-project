<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stepTypes = ['manual', 'automatic', 'approval', 'notification'];
        $roles = ['admin', 'manager', 'clerk', 'warehouse_staff'];

        return [
            'process_flow_id' => \App\Models\ProcessFlow::factory(),
            'name' => $this->faker->words(2, true) . ' Step',
            'step_order' => $this->faker->numberBetween(1, 10),
            'step_type' => $this->faker->randomElement($stepTypes),
            'assigned_role' => $this->faker->optional()->randomElement($roles),
            'auto_execute' => $this->faker->boolean(30),
            'conditions' => [
                'required_status' => $this->faker->optional()->randomElement(['pending', 'confirmed']),
                'min_amount' => $this->faker->optional()->randomFloat(2, 0, 100),
            ],
            'configuration' => [
                'timeout_minutes' => $this->faker->optional()->numberBetween(30, 1440),
                'notification_enabled' => $this->faker->boolean(70),
                'approval_required' => $this->faker->boolean(40),
            ],
        ];
    }
}
