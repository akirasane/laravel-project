<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderStatusHistoryRequest extends FormRequest
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
            'previous_status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'new_status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded|different:previous_status',
            'changed_by_type' => 'required|in:user,system,api,webhook',
            'changed_by_id' => 'nullable|exists:users,id',
            'reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'metadata.ip_address' => 'nullable|ip',
            'metadata.user_agent' => 'nullable|string|max:500',
            'metadata.source' => 'nullable|in:web,api,webhook,system',
            'is_reversible' => 'required|boolean',
            'reversed_at' => 'nullable|date',
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
            'previous_status.required' => 'Previous status is required.',
            'previous_status.in' => 'Previous status must be a valid order status.',
            'new_status.required' => 'New status is required.',
            'new_status.in' => 'New status must be a valid order status.',
            'new_status.different' => 'New status must be different from previous status.',
            'changed_by_type.required' => 'Change type is required.',
            'changed_by_type.in' => 'Change type must be one of: user, system, api, webhook.',
            'changed_by_id.exists' => 'Selected user does not exist.',
            'is_reversible.required' => 'Reversible flag is required.',
            'metadata.ip_address.ip' => 'IP address must be a valid IP address.',
            'metadata.source.in' => 'Source must be one of: web, api, webhook, system.',
        ];
    }
}