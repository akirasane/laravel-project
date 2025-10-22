<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRequestRequest extends FormRequest
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
            'return_authorization_number' => 'nullable|string|max:255|unique:return_requests,return_authorization_number,' . $this->route('return_request'),
            'return_type' => 'required|in:in_store,mail',
            'reason_code' => 'required|in:defective,wrong_item,not_as_described,changed_mind,damaged_shipping,other',
            'reason_description' => 'nullable|string|max:1000',
            'status' => 'required|in:requested,approved,rejected,in_transit,received,processed,completed,cancelled',
            'return_amount' => 'required|numeric|min:0|max:999999.99',
            'items_to_return' => 'required|array|min:1',
            'items_to_return.*.item_id' => 'required|integer',
            'items_to_return.*.quantity' => 'required|integer|min:1',
            'shipping_label_url' => 'nullable|url|max:500',
            'tracking_number' => 'nullable|string|max:255',
            'requested_at' => 'nullable|date',
            'approved_at' => 'nullable|date|after_or_equal:requested_at',
            'received_at' => 'nullable|date|after_or_equal:approved_at',
            'processed_at' => 'nullable|date|after_or_equal:received_at',
            'processed_by' => 'nullable|exists:users,id',
            'processing_notes' => 'nullable|string|max:1000',
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
            'return_authorization_number.unique' => 'This return authorization number already exists.',
            'return_type.required' => 'Return type is required.',
            'return_type.in' => 'Return type must be either in_store or mail.',
            'reason_code.required' => 'Reason code is required.',
            'reason_code.in' => 'Reason code must be one of: defective, wrong_item, not_as_described, changed_mind, damaged_shipping, other.',
            'status.required' => 'Return status is required.',
            'status.in' => 'Return status must be one of: requested, approved, rejected, in_transit, received, processed, completed, cancelled.',
            'return_amount.required' => 'Return amount is required.',
            'return_amount.numeric' => 'Return amount must be a valid number.',
            'items_to_return.required' => 'Items to return are required.',
            'items_to_return.min' => 'At least one item must be selected for return.',
            'items_to_return.*.item_id.required' => 'Item ID is required for each return item.',
            'items_to_return.*.quantity.required' => 'Quantity is required for each return item.',
            'items_to_return.*.quantity.min' => 'Quantity must be at least 1.',
            'approved_at.after_or_equal' => 'Approval date must be after or equal to request date.',
            'received_at.after_or_equal' => 'Received date must be after or equal to approval date.',
            'processed_at.after_or_equal' => 'Processing date must be after or equal to received date.',
        ];
    }
}