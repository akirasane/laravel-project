<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'previous_status',
        'new_status',
        'changed_by_type',
        'changed_by_id',
        'reason',
        'metadata',
        'is_reversible',
        'reversed_at'
    ];

    protected $casts = [
        'metadata' => 'json',
        'is_reversible' => 'boolean',
        'reversed_at' => 'datetime'
    ];

    /**
     * Get the order that owns this status history.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who changed the status.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }

    /**
     * Scope to get reversible status changes.
     */
    public function scopeReversible($query)
    {
        return $query->where('is_reversible', true)->whereNull('reversed_at');
    }

    /**
     * Scope to filter by status change type.
     */
    public function scopeByChangeType($query, string $type)
    {
        return $query->where('changed_by_type', $type);
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'previous_status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'new_status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'changed_by_type' => 'required|in:user,system,api,webhook',
            'changed_by_id' => 'nullable|exists:users,id',
            'reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'is_reversible' => 'required|boolean',
            'reversed_at' => 'nullable|date',
        ];
    }
}
