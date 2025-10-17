<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AuditTrailService
{
    /**
     * Log authentication events.
     */
    public function logAuthentication(string $event, array $context = []): void
    {
        if (!config('security.audit.events.authentication')) {
            return;
        }

        $this->logAuditEvent('authentication', $event, $context);
    }

    /**
     * Log authorization events.
     */
    public function logAuthorization(string $event, array $context = []): void
    {
        if (!config('security.audit.events.authorization')) {
            return;
        }

        $this->logAuditEvent('authorization', $event, $context);
    }

    /**
     * Log data change events.
     */
    public function logDataChange(string $model, string $action, array $changes = [], $modelId = null): void
    {
        if (!config('security.audit.events.data_changes')) {
            return;
        }

        $context = [
            'model' => $model,
            'model_id' => $modelId,
            'action' => $action,
            'changes' => $this->sanitizeChanges($changes),
        ];

        $this->logAuditEvent('data_change', $action, $context);
    }

    /**
     * Log administrative actions.
     */
    public function logAdminAction(string $action, array $context = []): void
    {
        if (!config('security.audit.events.admin_actions')) {
            return;
        }

        $this->logAuditEvent('admin_action', $action, $context);
    }

    /**
     * Log API calls.
     */
    public function logApiCall(string $endpoint, string $method, array $context = []): void
    {
        if (!config('security.audit.events.api_calls')) {
            return;
        }

        $context = array_merge($context, [
            'endpoint' => $endpoint,
            'method' => $method,
        ]);

        $this->logAuditEvent('api_call', 'request', $context);
    }

    /**
     * Log security events.
     */
    public function logSecurityEvent(string $event, string $severity = 'warning', array $context = []): void
    {
        $logData = $this->buildLogData('security_event', $event, $context);
        $logData['severity'] = $severity;

        Log::channel('security')->log($severity, "Security Event: {$event}", $logData);
    }

    /**
     * Log workflow events.
     */
    public function logWorkflowEvent(string $event, array $context = []): void
    {
        $logData = $this->buildLogData('workflow', $event, $context);
        Log::channel('workflow')->info("Workflow Event: {$event}", $logData);
    }

    /**
     * Log platform API events.
     */
    public function logPlatformApiEvent(string $platform, string $event, array $context = []): void
    {
        $context['platform'] = $platform;
        $logData = $this->buildLogData('platform_api', $event, $context);
        Log::channel('platform_api')->info("Platform API Event: {$platform} - {$event}", $logData);
    }

    /**
     * Log performance metrics.
     */
    public function logPerformanceMetric(string $metric, $value, array $context = []): void
    {
        $logData = $this->buildLogData('performance', $metric, $context);
        $logData['metric_value'] = $value;
        $logData['timestamp'] = Carbon::now()->toISOString();

        Log::channel('performance')->info("Performance Metric: {$metric}", $logData);
    }

    /**
     * Log a general audit event.
     */
    private function logAuditEvent(string $category, string $event, array $context = []): void
    {
        $logData = $this->buildLogData($category, $event, $context);
        
        Log::channel(config('security.audit.log_channel', 'audit'))
            ->info("Audit Event: {$category} - {$event}", $logData);
    }

    /**
     * Build standardized log data structure.
     */
    private function buildLogData(string $category, string $event, array $context = []): array
    {
        $request = request();
        $user = Auth::user();

        return [
            'category' => $category,
            'event' => $event,
            'timestamp' => Carbon::now()->toISOString(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'session_id' => $request?->session()?->getId(),
            'request_id' => $request?->header('X-Request-ID') ?? uniqid(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'context' => $context,
        ];
    }

    /**
     * Sanitize sensitive data from changes array.
     */
    private function sanitizeChanges(array $changes): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'credentials',
        ];

        foreach ($changes as $field => $value) {
            if (in_array($field, $sensitiveFields)) {
                $changes[$field] = '[REDACTED]';
            }
        }

        return $changes;
    }

    /**
     * Get audit trail for a specific model.
     */
    public function getAuditTrail(string $model, $modelId, int $limit = 50): array
    {
        // This would typically query a database table
        // For now, we'll return a placeholder structure
        return [
            'model' => $model,
            'model_id' => $modelId,
            'events' => [],
            'total_count' => 0,
        ];
    }

    /**
     * Clean up old audit logs based on retention policy.
     */
    public function cleanupOldLogs(): void
    {
        $retentionDays = config('security.audit.retention_days', 365);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        // This would typically clean up database records
        // For file-based logs, Laravel's daily driver handles rotation
        
        Log::channel('audit')->info('Audit log cleanup completed', [
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);
    }
}