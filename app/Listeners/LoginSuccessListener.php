<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LoginSuccessListener
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
        $user = $event->user;
        
        // Reset failed login attempts
        $user->resetFailedAttempts();
        
        // Log successful login
        \App\Models\AuditLog::log(
            'login_success',
            'authentication',
            "Successful login for user: {$user->email}",
            $user,
            [
                'guard' => $event->guard ?? 'web',
                'remember' => $event->remember ?? false,
            ],
            'low'
        );
        
        // Create or update user session
        \App\Models\UserSession::createOrUpdate($user, session()->getId());
        
        // Check if password needs to be changed
        if ($user->mustChangePassword()) {
            session(['must_change_password' => true]);
        }
        
        // Regenerate session ID for security
        if (config('security.session.regenerate_on_login', true)) {
            session()->regenerate();
        }
    }
}
