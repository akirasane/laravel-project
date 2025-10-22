<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $endTime = microtime(true);
        
        // Only log if audit is enabled
        if (!config('security.audit.enabled', true)) {
            return $response;
        }
        
        $user = $request->user();
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();
        $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds
        
        // Determine if this should be logged
        $shouldLog = $this->shouldLogRequest($request, $response);
        
        if ($shouldLog) {
            $eventType = $this->getEventType($request, $response);
            $eventCategory = $this->getEventCategory($request);
            $riskLevel = $this->getRiskLevel($request, $response);
            
            $description = "{$method} {$path} - {$statusCode}";
            
            $metadata = [
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'query_params' => $request->query(),
            ];
            
            // Add request body for sensitive operations (excluding passwords)
            if ($this->isSensitiveOperation($request)) {
                $body = $request->all();
                unset($body['password'], $body['password_confirmation'], $body['current_password']);
                $metadata['request_body'] = $body;
            }
            
            \App\Models\AuditLog::log(
                $eventType,
                $eventCategory,
                $description,
                $user,
                $metadata,
                $riskLevel
            );
        }
        
        return $response;
    }
    
    /**
     * Determine if the request should be logged.
     */
    private function shouldLogRequest($request, $response): bool
    {
        $statusCode = $response->getStatusCode();
        
        // Always log authentication events
        if ($this->isAuthenticationEvent($request)) {
            return true;
        }
        
        // Always log admin actions
        if (config('security.audit.log_admin_actions') && $this->isAdminAction($request)) {
            return true;
        }
        
        // Log failed requests
        if ($statusCode >= 400) {
            return true;
        }
        
        // Log data modification requests
        if (config('security.audit.log_data_changes') && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the event type for the request.
     */
    private function getEventType($request, $response): string
    {
        if ($this->isAuthenticationEvent($request)) {
            return $response->getStatusCode() === 200 ? 'login_success' : 'login_failure';
        }
        
        if ($this->isAdminAction($request)) {
            return 'admin_action';
        }
        
        return strtolower($request->method()) . '_request';
    }
    
    /**
     * Get the event category for the request.
     */
    private function getEventCategory($request): string
    {
        if ($this->isAuthenticationEvent($request)) {
            return 'authentication';
        }
        
        if ($this->isAdminAction($request)) {
            return 'admin_action';
        }
        
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return 'data_change';
        }
        
        return 'access';
    }
    
    /**
     * Get the risk level for the request.
     */
    private function getRiskLevel($request, $response): string
    {
        $statusCode = $response->getStatusCode();
        
        // High risk for failed authentication
        if ($this->isAuthenticationEvent($request) && $statusCode !== 200) {
            return 'high';
        }
        
        // Medium risk for admin actions
        if ($this->isAdminAction($request)) {
            return 'medium';
        }
        
        // Medium risk for failed requests
        if ($statusCode >= 400) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Check if the request is an authentication event.
     */
    private function isAuthenticationEvent($request): bool
    {
        return $request->routeIs('login') || 
               $request->routeIs('filament.admin.auth.login') ||
               $request->routeIs('logout') ||
               $request->routeIs('filament.admin.auth.logout');
    }
    
    /**
     * Check if the request is an admin action.
     */
    private function isAdminAction($request): bool
    {
        return str_starts_with($request->path(), 'admin/') ||
               str_starts_with($request->path(), 'filament/');
    }
    
    /**
     * Check if the operation is sensitive and should have request body logged.
     */
    private function isSensitiveOperation($request): bool
    {
        return $this->isAuthenticationEvent($request) ||
               $this->isAdminAction($request) ||
               in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }
}
