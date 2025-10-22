<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowStepRequest extends FormRequest
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
            'process_flow_id' => 'required|exists:process_flows,id',
            'name' => 'required|string|max:255',
            'step_order' => 'required|integer|min:1',
            'step_type' => 'required|in:manual,automatic,approval,notification,billing,packing,return',
            'assigned_role' => 'nullable|string|max:100',
            'auto_execute' => 'required|boolean',
            'conditions' => 'nullable|array',
            'conditions.required_status' => 'nullable|string|max:50',
            'conditions.min_amount' => 'nullable|numeric|min:0',
            'conditions.max_amount' => 'nullable|numeric|min:0|gte:conditions.min_amount',
            'configuration' => 'nullable|array',
            'configuration.timeout_minutes' => 'nullable|integer|min:1|max:10080', // max 1 week
            'configuration.notification_enabled' => 'nullable|boolean',
            'configuration.approval_required' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'process_flow_id.required' => 'Process flow is required.',
            'process_flow_id.exists' => 'Selected process flow does not exist.',
            'name.required' => 'Step name is required.',
            'step_order.required' => 'Step order is required.',
            'step_order.min' => 'Step order must be at least 1.',
            'step_type.required' => 'Step type is required.',
            'step_type.in' => 'Step type must be one of: manual, automatic, approval, notification, billing, packing, return.',
            'auto_execute.required' => 'Auto execute setting is required.',
            'configuration.timeout_minutes.max' => 'Timeout cannot exceed 1 week (10080 minutes).',
        ];
    }
}