<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user has any of the required permissions
        if (!$user->hasAnyPermission($permissions)) {
            // Log unauthorized access attempt
            \App\Models\AuditLog::log(
                'unauthorized_access',
                'authorization',
                "Unauthorized access attempt to route: {$request->route()->getName()}",
                $user,
                [
                    'required_permissions' => $permissions,
                    'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                    'route' => $request->route()->getName(),
                    'url' => $request->url(),
                ],
                'high'
            );

            abort(403, 'Insufficient permissions. Required permissions: ' . implode(', ', $permissions));
        }

        return $next($request);
    }
}
