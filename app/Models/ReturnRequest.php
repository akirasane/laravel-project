<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'return_authorization_number',
        'return_type',
        'reason_code',
        'reason_description',
        'status',
        'return_amount',
        'items_to_return',
        'shipping_label_url',
        'tracking_number',
        'requested_at',
        'approved_at',
        'received_at',
        'processed_at',
        'processed_by',
        'processing_notes'
    ];

    protected $casts = [
        'items_to_return' => 'json',
        'return_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime'
    ];

    /**
     * Get the order that owns this return request.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who processed this return.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope to filter by return type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('return_type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending returns.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'requested');
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'return_authorization_number' => 'nullable|string|max:255|unique:return_requests,return_authorization_number',
            'return_type' => 'required|in:in_store,mail',
            'reason_code' => 'required|in:defective,wrong_item,not_as_described,changed_mind,damaged_shipping,other',
            'reason_description' => 'nullable|string|max:1000',
            'status' => 'required|in:requested,approved,rejected,in_transit,received,processed,completed,cancelled',
            'return_amount' => 'required|numeric|min:0|max:999999.99',
            'items_to_return' => 'required|array|min:1',
            'shipping_label_url' => 'nullable|url|max:500',
            'tracking_number' => 'nullable|string|max:255',
            'requested_at' => 'nullable|date',
            'approved_at' => 'nullable|date',
            'received_at' => 'nullable|date',
            'processed_at' => 'nullable|date',
            'processed_by' => 'nullable|exists:users,id',
            'processing_notes' => 'nullable|string|max:1000',
        ];
    }
}
