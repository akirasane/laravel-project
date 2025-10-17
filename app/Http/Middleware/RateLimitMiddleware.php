<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        $config = config("security.rate_limiting.{$type}");
        
        if (!$config) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request, $type);
        
        if (RateLimiter::tooManyAttempts($key, $config['max_attempts'])) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'message' => 'Too many attempts. Please try again in ' . $seconds . ' seconds.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $config['decay_minutes'] * 60);

        $response = $next($request);

        // Add rate limit headers
        $response->headers->add([
            'X-RateLimit-Limit' => $config['max_attempts'],
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $config['max_attempts']),
            'X-RateLimit-Reset' => RateLimiter::availableIn($key),
        ]);

        return $response;
    }

    /**
     * Resolve the rate limiting signature for the request.
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $identifier = $request->user()?->id ?? $request->ip();
        
        return sprintf(
            'rate_limit:%s:%s:%s',
            $type,
            $identifier,
            $request->route()?->getName() ?? $request->path()
        );
    }
}