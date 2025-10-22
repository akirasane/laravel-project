<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        $twoFactorService = app(\App\Services\TwoFactorAuthService::class);

        // Check if 2FA is required for this user
        if (!$twoFactorService->isTwoFactorRequired($user)) {
            return $next($request);
        }

        // Check if user has completed 2FA setup
        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup')
                ->with('message', 'Two-factor authentication is required. Please set it up to continue.');
        }

        // Check if device is remembered
        if ($twoFactorService->isDeviceRemembered($user)) {
            return $next($request);
        }

        // Check if user has verified 2FA in this session
        if (session('two_factor_verified') === $user->id) {
            return $next($request);
        }

        // Redirect to 2FA verification
        return redirect()->route('two-factor.verify')
            ->with('message', 'Please verify your two-factor authentication code to continue.');
    }
}
