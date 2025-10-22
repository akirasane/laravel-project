<?php

namespace Tests\Feature;

use App\Services\PlatformCredentialManager;
use App\Services\PlatformConnectorFactory;
use App\Services\SsrfProtectionService;
use App\Services\PlatformSecurityMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    private PlatformCredentialManager $credentialManager;
    private PlatformConnectorFactory $connectorFactory;
    private SsrfProtectionService $ssrfProtection;
    private PlatformSecurityMonitoringService $securityMonitoring;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->credentialManager = app(PlatformCredentialManager::class);
        $this->connectorFactory = app(PlatformConnectorFactory::class);
        $this->ssrfProtection = app(SsrfProtectionService::class);
        $this->securityMonitoring = app(PlatformSecurityMonitoringService::class);
    }

    /** @test */
    public function it_prevents_ssrf_attacks_with_private_ips()
    {
        $maliciousUrls = [
            'http://127.0.0.1:8080/admin',
            'https://192.168.1.1/config',
            'http://10.0.0.1/internal',
            'https://169.254.169.254/metadata', // AWS metadata service
            'http://localhost:3000/api',
        ];

        foreach ($maliciousUrls as $url) {
            $this->expectException(\InvalidArgumentException::class);
            $this->ssrfProtection->validateUrl($url);
        }
    }

    /** @test */
    public function it_only_allows_https_urls()
    {
        $httpUrls = [
            'http://api.shopee.com/orders',
            'ftp://files.lazada.com/data',
            'file:///etc/passwd',
        ];

        foreach ($httpUrls as $url) {
            $this->expectException(\InvalidArgumentException::class);
            $this->ssrfProtection->validateUrl($url);
        }
    }

    /** @test */
    public function it_validates_allowed_domains()
    {
        $allowedDomains = ['api.shopee.com', 'partner.shopeemobile.com'];
        
        // Valid domain should pass
        $validUrl = 'https://api.shopee.com/v2/orders';
        $this->assertTrue($this->ssrfProtection->validateUrl($validUrl, $allowedDomains));

        // Invalid domain should fail
        $invalidUrl = 'https://malicious.com/steal-data';
        $this->expectException(\InvalidArgumentException::class);
        $this->ssrfProtection->validateUrl($invalidUrl, $allowedDomains);
    }

    /** @test */
    public function it_encrypts_credentials_securely()
    {
        $credentials = [
            'partner_id' => '12345',
            'partner_key' => 'secret_key_12345678901234567890',
            'shop_id' => '67890'
        ];

        $result = $this->credentialManager->storeCredentials('shopee', $credentials);
        $this->assertTrue($result);

        $retrievedCredentials = $this->credentialManager->getCredentials('shopee');
        $this->assertEquals($credentials['partner_id'], $retrievedCredentials['partner_id']);
        $this->assertEquals($credentials['partner_key'], $retrievedCredentials['partner_key']);
    }

    /** @test */
    public function it_validates_credential_format()
    {
        // Test invalid Shopee credentials
        $invalidCredentials = [
            'partner_id' => 'not_numeric',
            'partner_key' => 'too_short',
            'shop_id' => 'also_not_numeric'
        ];

        $result = $this->credentialManager->storeCredentials('shopee', $invalidCredentials);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_sanitizes_credential_input()
    {
        $maliciousCredentials = [
            'partner_id' => '12345<script>alert("xss")</script>',
            'partner_key' => 'key_with_<script>_tags',
            'shop_id' => '67890'
        ];

        $this->credentialManager->storeCredentials('shopee', $maliciousCredentials);
        $retrievedCredentials = $this->credentialManager->getCredentials('shopee');

        // Should be sanitized
        $this->assertStringNotContainsString('<script>', $retrievedCredentials['partner_id']);
        $this->assertStringNotContainsString('<script>', $retrievedCredentials['partner_key']);
    }

    /** @test */
    public function it_detects_suspicious_activity()
    {
        // Simulate high frequency requests
        for ($i = 0; $i < 20; $i++) {
            $this->securityMonitoring->monitorSuspiciousActivity('shopee', [
                'method' => 'GET',
                'endpoint' => '/api/v2/orders',
                'status_code' => 200,
                'timestamp' => now()->timestamp
            ]);
        }

        $metrics = $this->securityMonitoring->getSecurityMetrics('shopee');
        $this->assertNotEmpty($metrics['suspicious_activity']);
    }

    /** @test */
    public function it_logs_api_interactions_securely()
    {
        Log::shouldReceive('channel')
            ->with('platform_api')
            ->andReturnSelf();
        
        Log::shouldReceive('info')
            ->once()
            ->with('Platform API Interaction', \Mockery::type('array'));

        $this->securityMonitoring->logApiInteraction('shopee', [
            'method' => 'GET',
            'endpoint' => '/api/v2/orders',
            'status_code' => 200,
            'access_token' => 'secret_token_should_be_redacted'
        ]);
    }

    /** @test */
    public function it_redacts_sensitive_information_in_logs()
    {
        $sensitiveData = [
            'method' => 'POST',
            'access_token' => 'secret_token',
            'partner_key' => 'secret_key',
            'password' => 'secret_password',
            'normal_field' => 'normal_value'
        ];

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->securityMonitoring);
        $method = $reflection->getMethod('sanitizeLogData');
        $method->setAccessible(true);

        $sanitized = $method->invoke($this->securityMonitoring, $sensitiveData);

        $this->assertEquals('[REDACTED]', $sanitized['access_token']);
        $this->assertEquals('[REDACTED]', $sanitized['partner_key']);
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('normal_value', $sanitized['normal_field']);
    }

    /** @test */
    public function it_checks_dependency_vulnerabilities()
    {
        $vulnerabilities = $this->securityMonitoring->checkDependencyVulnerabilities();
        
        // Should return an array (empty or with vulnerabilities)
        $this->assertIsArray($vulnerabilities);
        
        // If vulnerabilities found, they should have required fields
        foreach ($vulnerabilities as $vulnerability) {
            $this->assertArrayHasKey('package', $vulnerability);
            $this->assertArrayHasKey('version', $vulnerability);
            $this->assertArrayHasKey('vulnerability', $vulnerability);
            $this->assertArrayHasKey('severity', $vulnerability);
        }
    }

    /** @test */
    public function it_generates_security_report()
    {
        $report = $this->securityMonitoring->generateSecurityReport();

        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('platforms', $report);
        $this->assertArrayHasKey('vulnerabilities', $report);
        $this->assertArrayHasKey('summary', $report);

        // Check summary structure
        $this->assertArrayHasKey('total_platforms', $report['summary']);
        $this->assertArrayHasKey('platforms_with_issues', $report['summary']);
        $this->assertArrayHasKey('total_vulnerabilities', $report['summary']);
    }

    /** @test */
    public function it_validates_webhook_signatures()
    {
        $payload = '{"order_id":"12345","status":"shipped"}';
        $secret = 'webhook_secret_key';
        
        // Test each platform's signature verification
        $platforms = ['shopee', 'lazada', 'shopify', 'tiktok'];
        
        foreach ($platforms as $platform) {
            $connector = $this->connectorFactory->create($platform);
            
            // Generate valid signature
            $validSignature = hash_hmac('sha256', $payload, $secret);
            $this->assertTrue($connector->verifyWebhookSignature($payload, $validSignature, $secret));
            
            // Test invalid signature
            $invalidSignature = 'invalid_signature';
            $this->assertFalse($connector->verifyWebhookSignature($payload, $invalidSignature, $secret));
        }
    }

    /** @test */
    public function it_enforces_rate_limits()
    {
        Cache::flush(); // Clear any existing rate limit data
        
        $platform = 'shopee';
        $rateLimit = config("platforms.rate_limits.{$platform}.requests_per_minute", 100);
        
        // Simulate requests up to the rate limit
        for ($i = 0; $i < $rateLimit; $i++) {
            $this->securityMonitoring->monitorSuspiciousActivity($platform, [
                'method' => 'GET',
                'endpoint' => '/api/v2/orders',
                'status_code' => 200
            ]);
        }
        
        // Additional requests should trigger suspicious activity detection
        $this->securityMonitoring->monitorSuspiciousActivity($platform, [
            'method' => 'GET',
            'endpoint' => '/api/v2/orders',
            'status_code' => 200
        ]);
        
        $metrics = $this->securityMonitoring->getSecurityMetrics($platform);
        $this->assertNotEmpty($metrics['suspicious_activity']);
    }

    /** @test */
    public function it_handles_authentication_failures_securely()
    {
        $platform = 'shopee';
        
        // Simulate multiple authentication failures
        for ($i = 0; $i < 5; $i++) {
            $this->securityMonitoring->monitorSuspiciousActivity($platform, [
                'method' => 'POST',
                'endpoint' => '/api/v2/auth/token/get',
                'status_code' => 401
            ]);
        }
        
        $metrics = $this->securityMonitoring->getSecurityMetrics($platform);
        $this->assertGreaterThan(0, $metrics['failed_auth']);
    }

    /** @test */
    public function it_validates_configuration_schema()
    {
        $platforms = ['shopee', 'lazada', 'shopify', 'tiktok'];
        
        foreach ($platforms as $platform) {
            $schema = $this->connectorFactory->getConfigurationSchema($platform);
            
            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema);
            
            // Each field should have required properties
            foreach ($schema as $field => $config) {
                $this->assertArrayHasKey('type', $config);
                $this->assertArrayHasKey('required', $config);
                $this->assertArrayHasKey('description', $config);
            }
        }
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}