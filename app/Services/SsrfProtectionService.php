<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SsrfProtectionService
{
    private const PRIVATE_IP_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    private const BLOCKED_PORTS = [
        22,   // SSH
        23,   // Telnet
        25,   // SMTP
        53,   // DNS
        110,  // POP3
        143,  // IMAP
        993,  // IMAPS
        995,  // POP3S
        1433, // MSSQL
        3306, // MySQL
        5432, // PostgreSQL
        6379, // Redis
        27017, // MongoDB
    ];

    /**
     * Validate URL to prevent SSRF attacks.
     */
    public function validateUrl(string $url, array $allowedDomains = []): bool
    {
        try {
            $parsedUrl = parse_url($url);
            
            if (!$parsedUrl || !isset($parsedUrl['host'])) {
                throw new InvalidArgumentException('Invalid URL format');
            }

            // Ensure HTTPS only
            if (!isset($parsedUrl['scheme']) || $parsedUrl['scheme'] !== 'https') {
                throw new InvalidArgumentException('Only HTTPS URLs are allowed');
            }

            // Check port restrictions
            $port = $parsedUrl['port'] ?? 443;
            if (in_array($port, self::BLOCKED_PORTS)) {
                throw new InvalidArgumentException("Port {$port} is not allowed");
            }

            // Resolve hostname to IP
            $host = $parsedUrl['host'];
            $ip = gethostbyname($host);
            
            if ($ip === $host) {
                // If gethostbyname returns the same string, DNS resolution failed
                throw new InvalidArgumentException("Cannot resolve hostname: {$host}");
            }

            // Check if IP is private/local
            if ($this->isPrivateIp($ip)) {
                throw new InvalidArgumentException("Requests to private networks are not allowed: {$ip}");
            }

            // Validate against allowed domains if provided
            if (!empty($allowedDomains) && !$this->isDomainAllowed($host, $allowedDomains)) {
                throw new InvalidArgumentException("Domain {$host} is not in the allowed list");
            }

            // Additional checks for suspicious patterns
            $this->checkSuspiciousPatterns($url);

            Log::info('URL validation passed', [
                'url' => $url,
                'host' => $host,
                'ip' => $ip
            ]);

            return true;

        } catch (InvalidArgumentException $e) {
            Log::warning('URL validation failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if IP address is private or local.
     */
    private function isPrivateIp(string $ip): bool
    {
        // Use PHP's built-in filter first
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        // Additional manual checks for edge cases
        foreach (self::PRIVATE_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is within a CIDR range.
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int)$bits);
        }
        
        return $this->ipv4InRange($ip, $subnet, (int)$bits);
    }

    /**
     * Check if IPv4 address is in range.
     */
    private function ipv4InRange(string $ip, string $subnet, int $bits): bool
    {
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        
        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * Check if IPv6 address is in range.
     */
    private function ipv6InRange(string $ip, string $subnet, int $bits): bool
    {
        $ip = inet_pton($ip);
        $subnet = inet_pton($subnet);
        
        if ($ip === false || $subnet === false) {
            return false;
        }

        $bytesToCheck = intval($bits / 8);
        $bitsToCheck = $bits % 8;

        for ($i = 0; $i < $bytesToCheck; $i++) {
            if ($ip[$i] !== $subnet[$i]) {
                return false;
            }
        }

        if ($bitsToCheck > 0) {
            $mask = 0xFF << (8 - $bitsToCheck);
            return (ord($ip[$bytesToCheck]) & $mask) === (ord($subnet[$bytesToCheck]) & $mask);
        }

        return true;
    }

    /**
     * Check if domain is in allowed list.
     */
    private function isDomainAllowed(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check for suspicious URL patterns.
     */
    private function checkSuspiciousPatterns(string $url): void
    {
        $suspiciousPatterns = [
            '/localhost/i',
            '/127\.0\.0\.1/',
            '/0\.0\.0\.0/',
            '/\[::\]/',
            '/file:\/\//i',
            '/ftp:\/\//i',
            '/gopher:\/\//i',
            '/dict:\/\//i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                throw new InvalidArgumentException('URL contains suspicious patterns');
            }
        }
    }

    /**
     * Validate and sanitize URL parameters.
     */
    public function sanitizeUrlParameters(array $parameters): array
    {
        $sanitized = [];
        
        foreach ($parameters as $key => $value) {
            // Remove potentially dangerous characters
            $cleanKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
            
            if (is_string($value)) {
                // Basic sanitization for string values
                $cleanValue = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
                $sanitized[$cleanKey] = $cleanValue;
            } elseif (is_array($value)) {
                // Recursively sanitize array values
                $sanitized[$cleanKey] = $this->sanitizeUrlParameters($value);
            } else {
                $sanitized[$cleanKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get allowed domains for a specific platform.
     */
    public function getAllowedDomainsForPlatform(string $platform): array
    {
        return config("platforms.endpoints.{$platform}.allowed_domains", []);
    }
}