<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderItemRequest extends FormRequest
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
            'product_id' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'product_sku' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1|max:9999',
            'unit_price' => 'required|numeric|min:0|max:999999.99',
            'total_price' => 'required|numeric|min:0|max:999999.99',
            'product_image_url' => 'nullable|url|max:500',
            'product_attributes' => 'nullable|array',
            'product_attributes.size' => 'nullable|string|max:50',
            'product_attributes.color' => 'nullable|string|max:50',
            'product_attributes.material' => 'nullable|string|max:100',
            'product_attributes.weight' => 'nullable|numeric|min:0',
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
            'product_id.required' => 'Product ID is required.',
            'product_name.required' => 'Product name is required.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 9999.',
            'unit_price.required' => 'Unit price is required.',
            'unit_price.numeric' => 'Unit price must be a valid number.',
            'total_price.required' => 'Total price is required.',
            'total_price.numeric' => 'Total price must be a valid number.',
            'product_image_url.url' => 'Product image URL must be a valid URL.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $quantity = $this->input('quantity', 0);
            $unitPrice = $this->input('unit_price', 0);
            $totalPrice = $this->input('total_price', 0);
            
            // Validate that total_price equals quantity * unit_price
            if (abs($totalPrice - ($quantity * $unitPrice)) > 0.01) {
                $validator->errors()->add('total_price', 'Total price must equal quantity × unit price.');
            }
        });
    }
}