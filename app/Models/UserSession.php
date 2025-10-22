<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'last_activity',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Create or update a user session.
     */
    public static function createOrUpdate(User $user, string $sessionId): self
    {
        $timeout = config('security.session.timeout_minutes', 60);
        
        return self::updateOrCreate(
            [
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ],
            [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'last_activity' => now(),
                'expires_at' => now()->addMinutes($timeout),
                'is_active' => true,
            ]
        );
    }

    /**
     * Terminate a session.
     */
    public function terminate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Clean up expired sessions.
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', now())
            ->orWhere('is_active', false)
            ->delete();
    }
}
