<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class PasswordHistory extends Model
{
    protected $fillable = [
        'user_id',
        'password_hash',
    ];

    /**
     * Get the user that owns the password history.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a password has been used before.
     */
    public static function hasBeenUsed(User $user, string $password): bool
    {
        $historyCount = config('security.password.history_count', 5);
        
        $recentPasswords = self::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($historyCount)
            ->pluck('password_hash');

        foreach ($recentPasswords as $hashedPassword) {
            if (Hash::check($password, $hashedPassword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a password to the history.
     */
    public static function addPassword(User $user, string $hashedPassword): void
    {
        self::create([
            'user_id' => $user->id,
            'password_hash' => $hashedPassword,
        ]);

        // Clean up old password history entries
        $historyCount = config('security.password.history_count', 5);
        $oldEntries = self::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->skip($historyCount)
            ->pluck('id');

        if ($oldEntries->isNotEmpty()) {
            self::whereIn('id', $oldEntries)->delete();
        }
    }
}
