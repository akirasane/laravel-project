<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingRecordRequest extends FormRequest
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
            'invoice_number' => 'required|string|max:255|unique:billing_records,invoice_number,' . $this->route('billing_record'),
            'billing_method' => 'required|in:pos_api,manual',
            'subtotal' => 'required|numeric|min:0|max:999999.99',
            'tax_amount' => 'required|numeric|min:0|max:999999.99',
            'discount_amount' => 'nullable|numeric|min:0|max:999999.99',
            'total_amount' => 'required|numeric|min:0|max:999999.99',
            'currency' => 'required|string|size:3',
            'payment_status' => 'required|in:pending,paid,partially_paid,refunded,cancelled,failed',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,digital_wallet,other',
            'pos_transaction_id' => 'nullable|string|max:255',
            'pos_response_data' => 'nullable|array',
            'billed_at' => 'nullable|date',
            'paid_at' => 'nullable|date|after_or_equal:billed_at',
            'created_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
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
            'invoice_number.required' => 'Invoice number is required.',
            'invoice_number.unique' => 'This invoice number already exists.',
            'billing_method.required' => 'Billing method is required.',
            'billing_method.in' => 'Billing method must be either pos_api or manual.',
            'subtotal.required' => 'Subtotal is required.',
            'subtotal.numeric' => 'Subtotal must be a valid number.',
            'tax_amount.required' => 'Tax amount is required.',
            'total_amount.required' => 'Total amount is required.',
            'currency.size' => 'Currency must be a 3-character code.',
            'payment_status.required' => 'Payment status is required.',
            'payment_status.in' => 'Payment status must be one of: pending, paid, partially_paid, refunded, cancelled, failed.',
            'payment_method.in' => 'Payment method must be one of: cash, card, bank_transfer, digital_wallet, other.',
            'paid_at.after_or_equal' => 'Payment date must be after or equal to billing date.',
        ];
    }
}