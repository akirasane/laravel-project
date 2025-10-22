<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user) {
            $sessionId = session()->getId();
            
            // Create or update user session
            \App\Models\UserSession::createOrUpdate($user, $sessionId);
            
            // Check for concurrent sessions limit
            $maxSessions = config('security.session.concurrent_sessions', 1);
            if ($maxSessions > 0) {
                $activeSessions = $user->activeSessions()->count();
                
                if ($activeSessions > $maxSessions) {
                    // Terminate oldest sessions
                    $sessionsToTerminate = $user->activeSessions()
                        ->orderBy('last_activity')
                        ->limit($activeSessions - $maxSessions)
                        ->get();
                    
                    foreach ($sessionsToTerminate as $session) {
                        $session->terminate();
                    }
                    
                    \App\Models\AuditLog::log(
                        'concurrent_session_limit',
                        'authentication',
                        "Terminated {$sessionsToTerminate->count()} sessions due to concurrent session limit",
                        $user,
                        ['terminated_sessions' => $sessionsToTerminate->count()],
                        'medium'
                    );
                }
            }
            
            // Check session timeout
            $timeout = config('security.session.timeout_minutes', 60);
            $lastActivity = session('last_activity', now());
            
            if (now()->diffInMinutes($lastActivity) > $timeout) {
                auth()->logout();
                session()->invalidate();
                session()->regenerateToken();
                
                \App\Models\AuditLog::log(
                    'session_timeout',
                    'authentication',
                    'Session timed out',
                    $user,
                    ['timeout_minutes' => $timeout],
                    'low'
                );
                
                return redirect()->route('login')->with('message', 'Your session has expired. Please log in again.');
            }
            
            session(['last_activity' => now()]);
        }

        return $next($request);
    }
}
