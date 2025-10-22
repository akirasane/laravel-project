<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles)) {
            // Log unauthorized access attempt
            \App\Models\AuditLog::log(
                'unauthorized_access',
                'authorization',
                "Unauthorized access attempt to route: {$request->route()->getName()}",
                $user,
                [
                    'required_roles' => $roles,
                    'user_roles' => $user->getRoleNames()->toArray(),
                    'route' => $request->route()->getName(),
                    'url' => $request->url(),
                ],
                'high'
            );

            abort(403, 'Insufficient permissions. Required roles: ' . implode(', ', $roles));
        }

        return $next($request);
    }
}
