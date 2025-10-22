<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LoginFailedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $credentials = $event->credentials;
        $email = $credentials['email'] ?? null;
        
        if (!$email) {
            return;
        }
        
        $user = \App\Models\User::where('email', $email)->first();
        
        if ($user) {
            // Increment failed login attempts
            $user->incrementFailedAttempts();
            
            $riskLevel = $user->failed_login_attempts >= 3 ? 'high' : 'medium';
            
            // Log failed login attempt
            \App\Models\AuditLog::log(
                'login_failure',
                'authentication',
                "Failed login attempt for user: {$user->email}",
                $user,
                [
                    'failed_attempts' => $user->failed_login_attempts,
                    'is_locked' => $user->isLocked(),
                    'guard' => $event->guard ?? 'web',
                ],
                $riskLevel
            );
            
            // Notify admin if account gets locked
            if ($user->isLocked() && config('security.lockout.notify_admin', true)) {
                // TODO: Implement admin notification
                \Log::warning("User account locked: {$user->email}", [
                    'user_id' => $user->id,
                    'failed_attempts' => $user->failed_login_attempts,
                    'locked_until' => $user->locked_until,
                ]);
            }
        } else {
            // Log failed login attempt for non-existent user
            \App\Models\AuditLog::log(
                'login_failure_unknown_user',
                'authentication',
                "Failed login attempt for unknown email: {$email}",
                null,
                [
                    'attempted_email' => $email,
                    'guard' => $event->guard ?? 'web',
                ],
                'high'
            );
        }
    }
}
