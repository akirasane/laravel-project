<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class PlatformSecurityMonitoringService
{
    private const SUSPICIOUS_ACTIVITY_THRESHOLD = 10;
    private const RATE_LIMIT_VIOLATION_THRESHOLD = 5;
    private const FAILED_AUTH_THRESHOLD = 3;

    /**
     * Log platform API interaction.
     */
    public function logApiInteraction(string $platform, array $data): void
    {
        $sanitizedData = $this->sanitizeLogData($data);
        
        Log::channel('platform_api')->info('Platform API Interaction', [
            'platform' => $platform,
            'timestamp' => now()->toISOString(),
            'method' => $sanitizedData['method'] ?? 'unknown',
            'endpoint' => $sanitizedData['endpoint'] ?? 'unknown',
            'status_code' => $sanitizedData['status_code'] ?? null,
            'response_time' => $sanitizedData['response_time'] ?? null,
            'request_size' => $sanitizedData['request_size'] ?? null,
            'response_size' => $sanitizedData['response_size'] ?? null,
            'user_agent' => $sanitizedData['user_agent'] ?? null,
            'ip_address' => $sanitizedData['ip_address'] ?? null,
        ]);

        // Store in audit log for compliance
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'platform_api_call',
            'model_type' => 'Platform',
            'model_id' => $platform,
            'old_values' => null,
            'new_values' => $sanitizedData,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Monitor for suspicious API activity.
     */
    public function monitorSuspiciousActivity(string $platform, array $data): void
    {
        $cacheKey = "suspicious_activity_{$platform}";
        $violations = Cache::get($cacheKey, []);
        
        // Check for various suspicious patterns
        $suspiciousPatterns = [
            'high_frequency_requests' => $this->detectHighFrequencyRequests($platform, $data),
            'unusual_endpoints' => $this->detectUnusualEndpoints($platform, $data),
            'failed_authentications' => $this->detectFailedAuthentications($platform, $data),
            'large_response_sizes' => $this->detectLargeResponses($platform, $data),
            'error_rate_spike' => $this->detectErrorRateSpike($platform, $data),
        ];

        foreach ($suspiciousPatterns as $pattern => $detected) {
            if ($detected) {
                $violations[] = [
                    'pattern' => $pattern,
                    'timestamp' => now()->toISOString(),
                    'data' => $this->sanitizeLogData($data),
                ];

                Log::warning('Suspicious platform activity detected', [
                    'platform' => $platform,
                    'pattern' => $pattern,
                    'data' => $this->sanitizeLogData($data)
                ]);
            }
        }

        // Clean old violations (keep only last hour)
        $violations = array_filter($violations, function ($violation) {
            return Carbon::parse($violation['timestamp'])->isAfter(now()->subHour());
        });

        Cache::put($cacheKey, $violations, 3600); // 1 hour TTL

        // Alert if threshold exceeded
        if (count($violations) >= self::SUSPICIOUS_ACTIVITY_THRESHOLD) {
            $this->alertSecurityTeam($platform, $violations);
        }
    }

    /**
     * Detect high frequency requests.
     */
    private function detectHighFrequencyRequests(string $platform, array $data): bool
    {
        $cacheKey = "request_frequency_{$platform}";
        $requests = Cache::get($cacheKey, []);
        
        $requests[] = now()->timestamp;
        
        // Keep only requests from last minute
        $requests = array_filter($requests, function ($timestamp) {
            return $timestamp > (time() - 60);
        });

        Cache::put($cacheKey, $requests, 60);

        // Check if exceeding rate limits
        $rateLimit = config("platforms.rate_limits.{$platform}.requests_per_minute", 60);
        return count($requests) > ($rateLimit * 1.5); // 150% of rate limit
    }

    /**
     * Detect unusual API endpoints.
     */
    private function detectUnusualEndpoints(string $platform, array $data): bool
    {
        $endpoint = $data['endpoint'] ?? '';
        
        // Define common endpoints for each platform
        $commonEndpoints = [
            'shopee' => ['/api/v2/order/get_order_list', '/api/v2/auth/token/get'],
            'lazada' => ['/orders/get', '/auth/token/create'],
            'shopify' => ['/orders.json', '/shop.json'],
            'tiktok' => ['/api/orders/search', '/api/fulfillment/ship'],
        ];

        $platformEndpoints = $commonEndpoints[$platform] ?? [];
        
        // Check if endpoint is in common list
        foreach ($platformEndpoints as $commonEndpoint) {
            if (str_contains($endpoint, $commonEndpoint)) {
                return false;
            }
        }

        return !empty($endpoint); // Unusual if not empty and not in common list
    }

    /**
     * Detect failed authentications.
     */
    private function detectFailedAuthentications(string $platform, array $data): bool
    {
        $statusCode = $data['status_code'] ?? 200;
        
        if (!in_array($statusCode, [401, 403])) {
            return false;
        }

        $cacheKey = "failed_auth_{$platform}";
        $failures = Cache::get($cacheKey, 0);
        $failures++;
        
        Cache::put($cacheKey, $failures, 300); // 5 minutes TTL

        return $failures >= self::FAILED_AUTH_THRESHOLD;
    }

    /**
     * Detect large response sizes.
     */
    private function detectLargeResponses(string $platform, array $data): bool
    {
        $responseSize = $data['response_size'] ?? 0;
        $maxSize = config('platforms.max_request_size', 1048576); // 1MB default
        
        return $responseSize > ($maxSize * 2); // Alert if response is 2x max request size
    }

    /**
     * Detect error rate spikes.
     */
    private function detectErrorRateSpike(string $platform, array $data): bool
    {
        $statusCode = $data['status_code'] ?? 200;
        $isError = $statusCode >= 400;
        
        $cacheKey = "error_rate_{$platform}";
        $stats = Cache::get($cacheKey, ['total' => 0, 'errors' => 0]);
        
        $stats['total']++;
        if ($isError) {
            $stats['errors']++;
        }

        Cache::put($cacheKey, $stats, 300); // 5 minutes TTL

        // Alert if error rate > 20% and we have at least 10 requests
        if ($stats['total'] >= 10) {
            $errorRate = $stats['errors'] / $stats['total'];
            return $errorRate > 0.2;
        }

        return false;
    }

    /**
     * Alert security team about suspicious activity.
     */
    private function alertSecurityTeam(string $platform, array $violations): void
    {
        Log::critical('Security alert: Suspicious platform activity threshold exceeded', [
            'platform' => $platform,
            'violation_count' => count($violations),
            'violations' => $violations,
            'timestamp' => now()->toISOString()
        ]);

        // TODO: Implement actual alerting mechanism (email, Slack, etc.)
        // This could integrate with your notification system
    }

    /**
     * Sanitize log data to remove sensitive information.
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveFields = config('platforms.logging.sensitive_fields', [
            'password', 'secret', 'key', 'token', 'access_token', 'refresh_token',
            'partner_key', 'app_secret', 'authorization'
        ]);

        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveFields as $sensitiveField) {
                if (str_contains($keyLower, strtolower($sensitiveField))) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get security metrics for a platform.
     */
    public function getSecurityMetrics(string $platform): array
    {
        $cacheKeys = [
            'suspicious_activity' => "suspicious_activity_{$platform}",
            'request_frequency' => "request_frequency_{$platform}",
            'failed_auth' => "failed_auth_{$platform}",
            'error_rate' => "error_rate_{$platform}",
        ];

        $metrics = [];
        foreach ($cacheKeys as $metric => $cacheKey) {
            $metrics[$metric] = Cache::get($cacheKey, []);
        }

        return $metrics;
    }

    /**
     * Check platform dependency vulnerabilities.
     */
    public function checkDependencyVulnerabilities(): array
    {
        $vulnerabilities = [];

        try {
            // Check for known vulnerable packages
            $composerLock = json_decode(file_get_contents(base_path('composer.lock')), true);
            $packages = $composerLock['packages'] ?? [];

            foreach ($packages as $package) {
                if ($this->isVulnerablePackage($package)) {
                    $vulnerabilities[] = [
                        'package' => $package['name'],
                        'version' => $package['version'],
                        'vulnerability' => 'Known security vulnerability',
                        'severity' => 'high'
                    ];
                }
            }

            Log::info('Dependency vulnerability scan completed', [
                'vulnerabilities_found' => count($vulnerabilities)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check dependency vulnerabilities', [
                'error' => $e->getMessage()
            ]);
        }

        return $vulnerabilities;
    }

    /**
     * Check if a package has known vulnerabilities.
     */
    private function isVulnerablePackage(array $package): bool
    {
        // This is a simplified check. In production, you would integrate with
        // vulnerability databases like GitHub Security Advisories, Snyk, etc.
        
        $knownVulnerablePackages = [
            'guzzlehttp/guzzle' => ['< 7.4.5'],
            'symfony/http-kernel' => ['< 5.4.20', '< 6.2.7'],
            'laravel/framework' => ['< 9.52.7', '< 10.8.0'],
        ];

        $packageName = $package['name'];
        $packageVersion = $package['version'];

        if (!isset($knownVulnerablePackages[$packageName])) {
            return false;
        }

        $vulnerableVersions = $knownVulnerablePackages[$packageName];
        
        foreach ($vulnerableVersions as $vulnerableVersion) {
            if (version_compare($packageVersion, ltrim($vulnerableVersion, '< '), '<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate security report.
     */
    public function generateSecurityReport(): array
    {
        $platforms = ['shopee', 'lazada', 'shopify', 'tiktok'];
        $report = [
            'generated_at' => now()->toISOString(),
            'platforms' => [],
            'vulnerabilities' => $this->checkDependencyVulnerabilities(),
            'summary' => [
                'total_platforms' => count($platforms),
                'platforms_with_issues' => 0,
                'total_vulnerabilities' => 0,
            ]
        ];

        foreach ($platforms as $platform) {
            $metrics = $this->getSecurityMetrics($platform);
            $hasIssues = !empty($metrics['suspicious_activity']) || 
                        !empty($metrics['failed_auth']) ||
                        ($metrics['error_rate']['errors'] ?? 0) > 0;

            $report['platforms'][$platform] = [
                'metrics' => $metrics,
                'has_security_issues' => $hasIssues,
            ];

            if ($hasIssues) {
                $report['summary']['platforms_with_issues']++;
            }
        }

        $report['summary']['total_vulnerabilities'] = count($report['vulnerabilities']);

        return $report;
    }
}