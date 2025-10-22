<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'invoice_number',
        'billing_method',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'payment_status',
        'payment_method',
        'pos_transaction_id',
        'pos_response_data',
        'billed_at',
        'paid_at',
        'created_by',
        'notes'
    ];

    protected $casts = [
        'pos_response_data' => 'json',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billed_at' => 'datetime',
        'paid_at' => 'datetime'
    ];

    /**
     * Get the order that owns this billing record.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who created this billing record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter by billing method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('billing_method', $method);
    }

    /**
     * Scope to filter by payment status.
     */
    public function scopeByPaymentStatus($query, string $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope to get paid records.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope to get pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'invoice_number' => 'required|string|max:255|unique:billing_records,invoice_number',
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
            'paid_at' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
