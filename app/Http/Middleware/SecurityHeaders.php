<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get security configuration
        $config = config('security.headers');

        // HTTP Strict Transport Security (HSTS)
        if ($config['hsts']['enabled'] && $request->isSecure()) {
            $hstsValue = 'max-age=' . $config['hsts']['max_age'];
            
            if ($config['hsts']['include_subdomains']) {
                $hstsValue .= '; includeSubDomains';
            }
            
            if ($config['hsts']['preload']) {
                $hstsValue .= '; preload';
            }
            
            $response->headers->set('Strict-Transport-Security', $hstsValue);
        }

        // Content Security Policy (CSP)
        if ($config['csp']['enabled']) {
            $response->headers->set('Content-Security-Policy', $config['csp']['policy']);
        }

        // X-Frame-Options
        if ($config['frame_options']) {
            $response->headers->set('X-Frame-Options', $config['frame_options']);
        }

        // X-Content-Type-Options
        if ($config['content_type_options']) {
            $response->headers->set('X-Content-Type-Options', $config['content_type_options']);
        }

        // Referrer Policy
        if ($config['referrer_policy']) {
            $response->headers->set('Referrer-Policy', $config['referrer_policy']);
        }

        // Permissions Policy
        if ($config['permissions_policy']) {
            $response->headers->set('Permissions-Policy', $config['permissions_policy']);
        }

        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}