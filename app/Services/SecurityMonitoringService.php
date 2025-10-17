<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SecurityMonitoringService
{
    private AuditTrailService $auditService;

    public function __construct(AuditTrailService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Monitor failed login attempts.
     */
    public function monitorFailedLogin(string $email, string $ipAddress): void
    {
        $key = "failed_login:{$email}:{$ipAddress}";
        $attempts = Cache::increment($key, 1);
        
        if ($attempts === 1) {
            Cache::put($key, 1, now()->addMinutes(15));
        }

        $this->auditService->logSecurityEvent('failed_login_attempt', 'warning', [
            'email' => $email,
            'ip_address' => $ipAddress,
            'attempt_count' => $attempts,
        ]);

        // Alert on suspicious activity
        if ($attempts >= 3) {
            $this->alertSuspiciousActivity('multiple_failed_logins', [
                'email' => $email,
                'ip_address' => $ipAddress,
                'attempts' => $attempts,
            ]);
        }
    }

    /**
     * Monitor successful login events.
     */
    public function monitorSuccessfulLogin(string $email, string $ipAddress): void
    {
        // Clear failed login attempts
        Cache::forget("failed_login:{$email}:{$ipAddress}");

        $this->auditService->logAuthentication('successful_login', [
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);

        // Check for login from new location
        $this->checkNewLocationLogin($email, $ipAddress);
    }

    /**
     * Monitor API rate limiting violations.
     */
    public function monitorRateLimitViolation(Request $request, string $type): void
    {
        $this->auditService->logSecurityEvent('rate_limit_violation', 'warning', [
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
        ]);

        // Track repeated violations
        $violationKey = "rate_violations:{$request->ip()}";
        $violations = Cache::increment($violationKey, 1);
        
        if ($violations === 1) {
            Cache::put($violationKey, 1, now()->addHour());
        }

        if ($violations >= 5) {
            $this->alertSuspiciousActivity('repeated_rate_limit_violations', [
                'ip_address' => $request->ip(),
                'violations' => $violations,
            ]);
        }
    }

    /**
     * Monitor suspicious file uploads.
     */
    public function monitorFileUpload(Request $request, $file): void
    {
        $suspiciousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'php', 'asp'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, $suspiciousExtensions)) {
            $this->auditService->logSecurityEvent('suspicious_file_upload', 'critical', [
                'filename' => $file->getClientOriginalName(),
                'extension' => $extension,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'ip_address' => $request->ip(),
            ]);
        }

        // Monitor large file uploads
        if ($file->getSize() > (50 * 1024 * 1024)) { // 50MB
            $this->auditService->logSecurityEvent('large_file_upload', 'info', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'ip_address' => $request->ip(),
            ]);
        }
    }

    /**
     * Monitor privilege escalation attempts.
     */
    public function monitorPrivilegeEscalation(string $userId, string $action, array $context = []): void
    {
        $this->auditService->logSecurityEvent('privilege_escalation_attempt', 'critical', array_merge([
            'user_id' => $userId,
            'action' => $action,
        ], $context));

        $this->alertSuspiciousActivity('privilege_escalation', [
            'user_id' => $userId,
            'action' => $action,
            'context' => $context,
        ]);
    }

    /**
     * Monitor SQL injection attempts.
     */
    public function monitorSqlInjectionAttempt(Request $request, string $input): void
    {
        $sqlPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b.*\bWHERE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\'.*OR.*\'.*=.*\')/i',
            '/(\".*OR.*\".*=.*\")/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->auditService->logSecurityEvent('sql_injection_attempt', 'critical', [
                    'input' => substr($input, 0, 500), // Limit logged input
                    'pattern_matched' => $pattern,
                    'ip_address' => $request->ip(),
                    'endpoint' => $request->path(),
                ]);

                $this->alertSuspiciousActivity('sql_injection', [
                    'ip_address' => $request->ip(),
                    'endpoint' => $request->path(),
                ]);
                break;
            }
        }
    }

    /**
     * Monitor XSS attempts.
     */
    public function monitorXssAttempt(Request $request, string $input): void
    {
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->auditService->logSecurityEvent('xss_attempt', 'high', [
                    'input' => substr($input, 0, 500),
                    'pattern_matched' => $pattern,
                    'ip_address' => $request->ip(),
                    'endpoint' => $request->path(),
                ]);

                $this->alertSuspiciousActivity('xss_attempt', [
                    'ip_address' => $request->ip(),
                    'endpoint' => $request->path(),
                ]);
                break;
            }
        }
    }

    /**
     * Check for login from new location.
     */
    private function checkNewLocationLogin(string $email, string $ipAddress): void
    {
        $knownIpsKey = "known_ips:{$email}";
        $knownIps = Cache::get($knownIpsKey, []);

        if (!in_array($ipAddress, $knownIps)) {
            $this->auditService->logSecurityEvent('new_location_login', 'info', [
                'email' => $email,
                'ip_address' => $ipAddress,
            ]);

            // Add to known IPs (keep last 10)
            $knownIps[] = $ipAddress;
            $knownIps = array_slice($knownIps, -10);
            Cache::put($knownIpsKey, $knownIps, now()->addDays(30));
        }
    }

    /**
     * Alert on suspicious activity.
     */
    private function alertSuspiciousActivity(string $type, array $context): void
    {
        // Log critical security alert
        Log::channel('security')->critical("Suspicious Activity Detected: {$type}", $context);

        // In a real implementation, this could:
        // - Send email alerts to administrators
        // - Send Slack notifications
        // - Trigger automated responses (IP blocking, account suspension)
        // - Update security dashboards
        // - Integrate with SIEM systems

        $this->auditService->logSecurityEvent('suspicious_activity_alert', 'critical', [
            'alert_type' => $type,
            'context' => $context,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Get security metrics for monitoring dashboard.
     */
    public function getSecurityMetrics(): array
    {
        // This would typically query logs or a metrics database
        return [
            'failed_logins_last_hour' => $this->getFailedLoginsCount(60),
            'rate_limit_violations_last_hour' => $this->getRateLimitViolationsCount(60),
            'suspicious_activities_last_24h' => $this->getSuspiciousActivitiesCount(1440),
            'unique_ips_last_24h' => $this->getUniqueIpsCount(1440),
            'api_calls_last_hour' => $this->getApiCallsCount(60),
        ];
    }

    /**
     * Get failed logins count for the specified minutes.
     */
    private function getFailedLoginsCount(int $minutes): int
    {
        // Placeholder implementation
        return 0;
    }

    /**
     * Get rate limit violations count for the specified minutes.
     */
    private function getRateLimitViolationsCount(int $minutes): int
    {
        // Placeholder implementation
        return 0;
    }

    /**
     * Get suspicious activities count for the specified minutes.
     */
    private function getSuspiciousActivitiesCount(int $minutes): int
    {
        // Placeholder implementation
        return 0;
    }

    /**
     * Get unique IPs count for the specified minutes.
     */
    private function getUniqueIpsCount(int $minutes): int
    {
        // Placeholder implementation
        return 0;
    }

    /**
     * Get API calls count for the specified minutes.
     */
    private function getApiCallsCount(int $minutes): int
    {
        // Placeholder implementation
        return 0;
    }
}