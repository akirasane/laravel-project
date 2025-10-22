<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'platform_order_id',
        'platform_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'total_amount',
        'currency',
        'status',
        'workflow_status',
        'order_date',
        'sync_status',
        'raw_data',
        'shipping_address',
        'billing_address',
        'notes'
    ];

    protected $casts = [
        'raw_data' => 'json',
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    /**
     * Get the order items for this order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the status history for this order.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Get the task assignments for this order.
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get the return request for this order.
     */
    public function returnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class);
    }

    /**
     * Get the billing record for this order.
     */
    public function billingRecord(): HasOne
    {
        return $this->hasOne(BillingRecord::class);
    }

    /**
     * Scope to filter orders by platform type.
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform_type', $platform);
    }

    /**
     * Scope to filter orders by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter orders by workflow status.
     */
    public function scopeByWorkflowStatus($query, string $workflowStatus)
    {
        return $query->where('workflow_status', $workflowStatus);
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
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
}
