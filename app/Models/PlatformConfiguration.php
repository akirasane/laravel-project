<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformConfiguration extends Model
{
    use HasFactory;
    protected $fillable = [
        'platform_type',
        'credentials',
        'sync_interval',
        'is_active',
        'last_sync',
        'settings'
    ];

    protected $casts = [
        'credentials' => 'encrypted:json',
        'settings' => 'json',
        'last_sync' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Scope to get active platform configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by platform type.
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform_type', $platform);
    }

    /**
     * Get the validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'platform_type' => 'required|in:shopee,lazada,shopify,tiktok|unique:platform_configurations,platform_type',
            'credentials' => 'required|array',
            'sync_interval' => 'required|integer|min:60|max:86400',
            'is_active' => 'required|boolean',
            'last_sync' => 'nullable|date',
            'settings' => 'nullable|array',
        ];
    }
}
