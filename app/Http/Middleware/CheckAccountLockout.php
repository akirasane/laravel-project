<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountLockout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for authentication routes
        if (!$request->routeIs('login') && !$request->routeIs('filament.admin.auth.login')) {
            return $next($request);
        }

        $email = $request->input('email');
        if (!$email) {
            return $next($request);
        }

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            return $next($request);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            $lockoutDuration = config('security.lockout.lockout_duration', 900);
            $remainingTime = $user->locked_until->diffInMinutes(now());
            
            // Log the lockout attempt
            \App\Models\AuditLog::log(
                'login_attempt_locked',
                'authentication',
                "Login attempt on locked account: {$user->email}",
                $user,
                ['remaining_lockout_minutes' => $remainingTime],
                'high'
            );

            return response()->json([
                'message' => "Account is locked. Please try again in {$remainingTime} minutes.",
                'locked_until' => $user->locked_until->toISOString(),
            ], 423);
        }

        // Check if account is inactive
        if (!$user->is_active) {
            \App\Models\AuditLog::log(
                'login_attempt_inactive',
                'authentication',
                "Login attempt on inactive account: {$user->email}",
                $user,
                [],
                'medium'
            );

            return response()->json([
                'message' => 'Account is inactive. Please contact administrator.',
            ], 403);
        }

        return $next($request);
    }
}
