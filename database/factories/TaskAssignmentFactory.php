<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $assignedAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $status = $this->faker->randomElement($statuses);
        
        $startedAt = null;
        $completedAt = null;
        
        if (in_array($status, ['in_progress', 'completed'])) {
            $startedAt = $this->faker->dateTimeBetween($assignedAt, 'now');
        }
        
        if ($status === 'completed') {
            $completedAt = $this->faker->dateTimeBetween($startedAt ?? $assignedAt, 'now');
        }

        return [
            'order_id' => \App\Models\Order::factory(),
            'workflow_step_id' => \App\Models\WorkflowStep::factory(),
            'assigned_to' => $this->faker->optional()->randomElement([1, 2, 3]), // Assuming some user IDs exist
            'status' => $status,
            'assigned_at' => $assignedAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'notes' => $this->faker->optional()->sentence(),
            'task_data' => [
                'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
                'estimated_duration' => $this->faker->optional()->numberBetween(15, 240), // minutes
            ],
        ];
    }
}
