<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'total_price',
        'product_image_url',
        'product_attributes'
    ];

    protected $casts = [
        'product_attributes' => 'json',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2'
    ];

    /**
     * Get the order that owns this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
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
        ];
    }
}
