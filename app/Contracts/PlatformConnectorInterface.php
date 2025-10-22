<?php

namespace App\Contracts;

use Illuminate\Support\Collection;
use Carbon\Carbon;

interface PlatformConnectorInterface
{
    /**
     * Authenticate with the platform using provided credentials.
     */
    public function authenticate(array $credentials): bool;

    /**
     * Validate credentials without storing them.
     */
    public function validateCredentials(array $credentials): bool;

    /**
     * Fetch orders from the platform since a specific date.
     */
    public function fetchOrders(Carbon $since = null): Collection;

    /**
     * Update order status on the platform.
     */
    public function updateOrderStatus(string $orderId, string $status): bool;

    /**
     * Get platform-specific configuration requirements.
     */
    public function getConfigurationSchema(): array;

    /**
     * Test connection to the platform.
     */
    public function testConnection(): bool;

    /**
     * Get platform rate limits.
     */
    public function getRateLimits(): array;

    /**
     * Verify webhook signature for incoming requests.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;
}