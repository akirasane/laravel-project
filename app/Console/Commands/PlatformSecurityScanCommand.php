<?php

namespace App\Console\Commands;

use App\Services\PlatformSecurityMonitoringService;
use App\Services\PlatformConnectorFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PlatformSecurityScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'platform:security-scan 
                            {--platform= : Specific platform to scan}
                            {--report : Generate detailed security report}
                            {--vulnerabilities : Check for dependency vulnerabilities}
                            {--fail-on-issues : Exit with error code if issues found}';

    /**
     * The console command description.
     */
    protected $description = 'Run security scans on platform integrations';

    private PlatformSecurityMonitoringService $securityMonitoring;
    private PlatformConnectorFactory $connectorFactory;

    public function __construct(
        PlatformSecurityMonitoringService $securityMonitoring,
        PlatformConnectorFactory $connectorFactory
    ) {
        parent::__construct();
        $this->securityMonitoring = $securityMonitoring;
        $this->connectorFactory = $connectorFactory;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting platform security scan...');
        
        $platform = $this->option('platform');
        $generateReport = $this->option('report');
        $checkVulnerabilities = $this->option('vulnerabilities');
        $failOnIssues = $this->option('fail-on-issues');
        
        $issuesFound = false;

        try {
            // Check specific platform or all platforms
            if ($platform) {
                $issuesFound = $this->scanPlatform($platform);
            } else {
                $issuesFound = $this->scanAllPlatforms();
            }

            // Check dependency vulnerabilities if requested
            if ($checkVulnerabilities) {
                $vulnerabilities = $this->checkVulnerabilities();
                if (!empty($vulnerabilities)) {
                    $issuesFound = true;
                }
            }

            // Generate detailed report if requested
            if ($generateReport) {
                $this->generateReport();
            }

            // Summary
            if ($issuesFound) {
                $this->error('Security scan completed with issues found.');
                
                if ($failOnIssues) {
                    return 1; // Exit with error code for CI/CD
                }
            } else {
                $this->info('Security scan completed successfully - no issues found.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Security scan failed: ' . $e->getMessage());
            Log::error('Platform security scan failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Scan a specific platform.
     */
    private function scanPlatform(string $platform): bool
    {
        $this->info("Scanning platform: {$platform}");
        
        if (!$this->connectorFactory->isSupported($platform)) {
            $this->error("Unsupported platform: {$platform}");
            return true;
        }

        $issuesFound = false;

        // Test connection
        $this->info('Testing platform connection...');
        if (!$this->connectorFactory->testConnection($platform)) {
            $this->error("Connection test failed for {$platform}");
            $issuesFound = true;
        } else {
            $this->info("Connection test passed for {$platform}");
        }

        // Check security metrics
        $this->info('Checking security metrics...');
        $metrics = $this->securityMonitoring->getSecurityMetrics($platform);
        
        if (!empty($metrics['suspicious_activity'])) {
            $this->warn("Suspicious activity detected for {$platform}: " . count($metrics['suspicious_activity']) . ' incidents');
            $issuesFound = true;
        }

        if (!empty($metrics['failed_auth'])) {
            $this->warn("Authentication failures detected for {$platform}: " . $metrics['failed_auth'] . ' failures');
            $issuesFound = true;
        }

        if (isset($metrics['error_rate']['errors']) && $metrics['error_rate']['errors'] > 0) {
            $errorRate = $metrics['error_rate']['errors'] / max($metrics['error_rate']['total'], 1);
            if ($errorRate > 0.1) { // 10% error rate threshold
                $this->warn("High error rate detected for {$platform}: " . round($errorRate * 100, 2) . '%');
                $issuesFound = true;
            }
        }

        return $issuesFound;
    }

    /**
     * Scan all supported platforms.
     */
    private function scanAllPlatforms(): bool
    {
        $platforms = $this->connectorFactory->getAvailablePlatforms();
        $issuesFound = false;

        $this->info('Scanning all platforms: ' . implode(', ', $platforms));

        foreach ($platforms as $platform) {
            if ($this->scanPlatform($platform)) {
                $issuesFound = true;
            }
            $this->newLine();
        }

        return $issuesFound;
    }

    /**
     * Check for dependency vulnerabilities.
     */
    private function checkVulnerabilities(): array
    {
        $this->info('Checking for dependency vulnerabilities...');
        
        $vulnerabilities = $this->securityMonitoring->checkDependencyVulnerabilities();
        
        if (empty($vulnerabilities)) {
            $this->info('No known vulnerabilities found in dependencies.');
        } else {
            $this->error('Found ' . count($vulnerabilities) . ' potential vulnerabilities:');
            
            $headers = ['Package', 'Version', 'Vulnerability', 'Severity'];
            $rows = [];
            
            foreach ($vulnerabilities as $vuln) {
                $rows[] = [
                    $vuln['package'],
                    $vuln['version'],
                    $vuln['vulnerability'],
                    $vuln['severity']
                ];
            }
            
            $this->table($headers, $rows);
        }

        return $vulnerabilities;
    }

    /**
     * Generate detailed security report.
     */
    private function generateReport(): void
    {
        $this->info('Generating detailed security report...');
        
        $report = $this->securityMonitoring->generateSecurityReport();
        
        // Display summary
        $this->info('Security Report Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Platforms', $report['summary']['total_platforms']],
                ['Platforms with Issues', $report['summary']['platforms_with_issues']],
                ['Total Vulnerabilities', $report['summary']['total_vulnerabilities']],
                ['Generated At', $report['generated_at']]
            ]
        );

        // Display platform details
        foreach ($report['platforms'] as $platform => $data) {
            $this->newLine();
            $this->info("Platform: {$platform}");
            
            if ($data['has_security_issues']) {
                $this->warn('  Status: Has security issues');
            } else {
                $this->info('  Status: No issues detected');
            }

            $metrics = $data['metrics'];
            if (!empty($metrics['suspicious_activity'])) {
                $this->warn('  Suspicious Activities: ' . count($metrics['suspicious_activity']));
            }
            
            if (!empty($metrics['failed_auth'])) {
                $this->warn('  Failed Authentications: ' . $metrics['failed_auth']);
            }
        }

        // Save report to file
        $reportPath = storage_path('logs/platform-security-report-' . now()->format('Y-m-d-H-i-s') . '.json');
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("Detailed report saved to: {$reportPath}");
    }
}