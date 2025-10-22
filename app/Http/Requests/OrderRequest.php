<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
            'platform_order_id' => 'required|string|max:255',
            'platform_type' => 'required|in:shopee,lazada,shopify,tiktok',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'total_amount' => 'required|numeric|min:0|max:999999.99',
            'currency' => 'required|string|size:3',
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'workflow_status' => 'required|in:new,in_progress,completed,on_hold',
            'order_date' => 'required|date',
            'sync_status' => 'required|in:synced,pending,failed',
            'raw_data' => 'nullable|json',
            'shipping_address' => 'nullable|string|max:500',
            'billing_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'platform_order_id.required' => 'Platform order ID is required.',
            'platform_type.in' => 'Platform type must be one of: shopee, lazada, shopify, tiktok.',
            'customer_name.required' => 'Customer name is required.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.numeric' => 'Total amount must be a valid number.',
            'currency.size' => 'Currency must be a 3-character code.',
            'status.in' => 'Status must be a valid order status.',
            'workflow_status.in' => 'Workflow status must be a valid workflow status.',
        ];
    }
}
