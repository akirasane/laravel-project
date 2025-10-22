<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'event_category',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'risk_level',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    /**
     * Get the user that owns the audit log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create an audit log entry.
     */
    public static function log(
        string $eventType,
        string $eventCategory,
        string $description,
        ?User $user = null,
        array $metadata = [],
        string $riskLevel = 'low'
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'event_category' => $eventCategory,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'risk_level' => $riskLevel,
        ]);
    }
}
