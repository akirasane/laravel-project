<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'workflow_step_id' => 'required|exists:workflow_steps,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:pending,in_progress,completed,cancelled,on_hold',
            'assigned_at' => 'nullable|date',
            'started_at' => 'nullable|date|after_or_equal:assigned_at',
            'completed_at' => 'nullable|date|after_or_equal:started_at',
            'notes' => 'nullable|string|max:1000',
            'task_data' => 'nullable|array',
            'task_data.priority' => 'nullable|in:low,medium,high,urgent',
            'task_data.estimated_duration' => 'nullable|integer|min:1|max:1440', // max 24 hours in minutes
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order is required.',
            'order_id.exists' => 'Selected order does not exist.',
            'workflow_step_id.required' => 'Workflow step is required.',
            'workflow_step_id.exists' => 'Selected workflow step does not exist.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'status.required' => 'Task status is required.',
            'status.in' => 'Task status must be one of: pending, in_progress, completed, cancelled, on_hold.',
            'started_at.after_or_equal' => 'Start time must be after or equal to assignment time.',
            'completed_at.after_or_equal' => 'Completion time must be after or equal to start time.',
            'task_data.priority.in' => 'Priority must be one of: low, medium, high, urgent.',
            'task_data.estimated_duration.max' => 'Estimated duration cannot exceed 24 hours (1440 minutes).',
        ];
    }
}