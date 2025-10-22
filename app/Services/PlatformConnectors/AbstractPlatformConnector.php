<?php

namespace App\Services\PlatformConnectors;

use App\Contracts\PlatformConnectorInterface;
use App\Services\PlatformCredentialManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;
use Exception;

abstract class AbstractPlatformConnector implements PlatformConnectorInterface
{
    protected PlatformCredentialManager $credentialManager;
    protected array $credentials;
    protected string $platformType;
    protected array $rateLimits;
    protected int $requestTimeout;
    protected int $maxRequestSize;

    public function __construct(PlatformCredentialManager $credentialManager)
    {
        $this->credentialManager = $credentialManager;
        $this->requestTimeout = (int) config('platforms.request_timeout', 30);
        $this->maxRequestSize = (int) config('platforms.max_request_size', 1048576); // 1MB
        $this->initializePlatformConfig();
    }

    /**
     * Initialize platform-specific configuration.
     */
    abstract protected function initializePlatformConfig(): void;

    /**
     * Get the base API URL for the platform.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Get platform-specific headers for API requests.
     */
    abstract protected function getHeaders(): array;

    /**
     * Transform platform-specific order data to normalized format.
     */
    abstract protected function normalizeOrderData(array $orderData): array;

    /**
     * Authenticate with the platform.
     */
    public function authenticate(array $credentials): bool
    {
        try {
            $this->credentials = $credentials;
            return $this->testConnection();
        } catch (Exception $e) {
            Log::error('Platform authentication failed', [
                'platform' => $this->platformType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate credentials without storing them.
     */
    public function validateCredentials(array $credentials): bool
    {
        $tempCredentials = $this->credentials;
        $this->credentials = $credentials;
        
        try {
            $result = $this->testConnection();
            $this->credentials = $tempCredentials;
            return $result;
        } catch (Exception $e) {
            $this->credentials = $tempCredentials;
            return false;
        }
    }

    /**
     * Create a secure HTTP client with SSRF protection.
     */
    protected function createHttpClient(): PendingRequest
    {
        return Http::timeout($this->requestTimeout)
            ->withHeaders($this->getHeaders())
            ->withOptions([
                'verify' => true, // Verify SSL certificates
                'allow_redirects' => [
                    'max' => 3,
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['https'] // Only allow HTTPS redirects
                ],
                'http_errors' => false, // Handle errors manually
            ])
            ->beforeSending(function ($request, $options) {
                // SSRF Protection: Validate URL
                $this->validateRequestUrl($request->url());
                
                // Log API request
                Log::channel('platform_api')->info('API Request', [
                    'platform' => $this->platformType,
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'headers' => array_keys($request->headers())
                ]);
            });
    }

    /**
     * Validate request URL to prevent SSRF attacks.
     */
    protected function validateRequestUrl(string $url): void
    {
        $parsedUrl = parse_url($url);
        
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            throw new Exception('Invalid URL provided');
        }

        // Check if URL uses HTTPS
        if ($parsedUrl['scheme'] !== 'https') {
            throw new Exception('Only HTTPS URLs are allowed');
        }

        // Prevent requests to private/local networks
        $host = $parsedUrl['host'];
        $ip = gethostbyname($host);
        
        if ($this->isPrivateIp($ip)) {
            throw new Exception('Requests to private networks are not allowed');
        }

        // Validate against allowed domains
        $allowedDomains = $this->getAllowedDomains();
        $hostAllowed = false;
        
        foreach ($allowedDomains as $domain) {
            if (str_ends_with($host, $domain)) {
                $hostAllowed = true;
                break;
            }
        }

        if (!$hostAllowed) {
            throw new Exception("Domain {$host} is not in the allowed list");
        }
    }

    /**
     * Check if IP address is private/local.
     */
    protected function isPrivateIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get allowed domains for this platform.
     */
    abstract protected function getAllowedDomains(): array;

    /**
     * Make a rate-limited API request.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $this->checkRateLimit();
        
        $client = $this->createHttpClient();
        $url = $this->getBaseUrl() . '/' . ltrim($endpoint, '/');

        try {
            $response = match (strtoupper($method)) {
                'GET' => $client->get($url, $data),
                'POST' => $client->post($url, $data),
                'PUT' => $client->put($url, $data),
                'DELETE' => $client->delete($url, $data),
                default => throw new Exception("Unsupported HTTP method: {$method}")
            };

            $this->updateRateLimit();
            
            // Log response
            Log::channel('platform_api')->info('API Response', [
                'platform' => $this->platformType,
                'status' => $response->status(),
                'response_size' => strlen($response->body())
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('API request failed', [
                'platform' => $this->platformType,
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check rate limiting before making request.
     */
    protected function checkRateLimit(): void
    {
        $key = "rate_limit_{$this->platformType}";
        $requests = Cache::get($key, 0);
        
        if ($requests >= $this->rateLimits['requests_per_minute']) {
            throw new Exception('Rate limit exceeded for platform: ' . $this->platformType);
        }
    }

    /**
     * Update rate limit counter.
     */
    protected function updateRateLimit(): void
    {
        $key = "rate_limit_{$this->platformType}";
        $requests = Cache::get($key, 0);
        Cache::put($key, $requests + 1, 60); // 1 minute TTL
    }

    /**
     * Get platform rate limits.
     */
    public function getRateLimits(): array
    {
        return $this->rateLimits;
    }

    /**
     * Load credentials from credential manager.
     */
    protected function loadCredentials(): bool
    {
        $credentials = $this->credentialManager->getCredentials($this->platformType);
        
        if (!$credentials) {
            Log::warning('No credentials found for platform', [
                'platform' => $this->platformType
            ]);
            return false;
        }

        $this->credentials = $credentials;
        return true;
    }

    /**
     * Verify webhook signature (default implementation).
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get environment-specific API endpoint.
     */
    protected function getEnvironmentEndpoint(string $sandboxUrl, string $productionUrl): string
    {
        return config('app.env') === 'production' ? $productionUrl : $sandboxUrl;
    }
}