<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessFlowRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:process_flows,name,' . $this->route('process_flow'),
            'description' => 'nullable|string|max:1000',
            'is_active' => 'required|boolean',
            'conditions' => 'nullable|array',
            'conditions.platform_type' => 'nullable|in:shopee,lazada,shopify,tiktok',
            'conditions.order_amount_min' => 'nullable|numeric|min:0',
            'conditions.order_amount_max' => 'nullable|numeric|min:0|gte:conditions.order_amount_min',
            'conditions.customer_type' => 'nullable|string|max:50',
            'created_by' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Process flow name is required.',
            'name.unique' => 'A process flow with this name already exists.',
            'is_active.required' => 'Active status is required.',
            'conditions.platform_type.in' => 'Platform type must be one of: shopee, lazada, shopify, tiktok.',
            'conditions.order_amount_min.numeric' => 'Minimum order amount must be a valid number.',
            'conditions.order_amount_max.gte' => 'Maximum order amount must be greater than or equal to minimum amount.',
        ];
    }
}