<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class CircuitBreakerService
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $serviceName;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $halfOpenMaxCalls;

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
        $this->failureThreshold = config('platforms.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeout = config('platforms.circuit_breaker.recovery_timeout', 60);
        $this->halfOpenMaxCalls = config('platforms.circuit_breaker.half_open_max_calls', 3);
    }

    /**
     * Execute a callable with circuit breaker protection.
     */
    public function call(callable $callback)
    {
        $state = $this->getState();

        switch ($state) {
            case self::STATE_OPEN:
                if ($this->shouldAttemptReset()) {
                    $this->setState(self::STATE_HALF_OPEN);
                    return $this->executeHalfOpen($callback);
                }
                throw new Exception("Circuit breaker is OPEN for service: {$this->serviceName}");

            case self::STATE_HALF_OPEN:
                return $this->executeHalfOpen($callback);

            case self::STATE_CLOSED:
            default:
                return $this->executeClosed($callback);
        }
    }

    /**
     * Execute callback in closed state.
     */
    private function executeClosed(callable $callback)
    {
        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * Execute callback in half-open state.
     */
    private function executeHalfOpen(callable $callback)
    {
        $halfOpenCalls = $this->getHalfOpenCalls();
        
        if ($halfOpenCalls >= $this->halfOpenMaxCalls) {
            throw new Exception("Circuit breaker HALF_OPEN max calls exceeded for service: {$this->serviceName}");
        }

        $this->incrementHalfOpenCalls();

        try {
            $result = $callback();
            $this->onHalfOpenSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onHalfOpenFailure();
            throw $e;
        }
    }

    /**
     * Handle successful execution.
     */
    private function onSuccess(): void
    {
        $this->resetFailureCount();
        Log::debug('Circuit breaker success', [
            'service' => $this->serviceName,
            'state' => $this->getState()
        ]);
    }

    /**
     * Handle failed execution.
     */
    private function onFailure(): void
    {
        $failures = $this->incrementFailureCount();
        
        Log::warning('Circuit breaker failure', [
            'service' => $this->serviceName,
            'failures' => $failures,
            'threshold' => $this->failureThreshold
        ]);

        if ($failures >= $this->failureThreshold) {
            $this->setState(self::STATE_OPEN);
            $this->setLastFailureTime(time());
            
            Log::error('Circuit breaker opened', [
                'service' => $this->serviceName,
                'failures' => $failures
            ]);
        }
    }

    /**
     * Handle successful execution in half-open state.
     */
    private function onHalfOpenSuccess(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetFailureCount();
        $this->resetHalfOpenCalls();
        
        Log::info('Circuit breaker closed after recovery', [
            'service' => $this->serviceName
        ]);
    }

    /**
     * Handle failed execution in half-open state.
     */
    private function onHalfOpenFailure(): void
    {
        $this->setState(self::STATE_OPEN);
        $this->setLastFailureTime(time());
        $this->resetHalfOpenCalls();
        
        Log::error('Circuit breaker reopened after half-open failure', [
            'service' => $this->serviceName
        ]);
    }

    /**
     * Check if circuit breaker should attempt reset.
     */
    private function shouldAttemptReset(): bool
    {
        $lastFailureTime = $this->getLastFailureTime();
        return $lastFailureTime && (time() - $lastFailureTime) >= $this->recoveryTimeout;
    }

    /**
     * Get current circuit breaker state.
     */
    private function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    /**
     * Set circuit breaker state.
     */
    private function setState(string $state): void
    {
        Cache::put($this->getStateKey(), $state, 3600); // 1 hour TTL
    }

    /**
     * Get failure count.
     */
    private function getFailureCount(): int
    {
        return Cache::get($this->getFailureCountKey(), 0);
    }

    /**
     * Increment failure count.
     */
    private function incrementFailureCount(): int
    {
        $key = $this->getFailureCountKey();
        $count = Cache::get($key, 0) + 1;
        Cache::put($key, $count, 3600); // 1 hour TTL
        return $count;
    }

    /**
     * Reset failure count.
     */
    private function resetFailureCount(): void
    {
        Cache::forget($this->getFailureCountKey());
    }

    /**
     * Get last failure time.
     */
    private function getLastFailureTime(): ?int
    {
        return Cache::get($this->getLastFailureTimeKey());
    }

    /**
     * Set last failure time.
     */
    private function setLastFailureTime(int $timestamp): void
    {
        Cache::put($this->getLastFailureTimeKey(), $timestamp, 3600); // 1 hour TTL
    }

    /**
     * Get half-open calls count.
     */
    private function getHalfOpenCalls(): int
    {
        return Cache::get($this->getHalfOpenCallsKey(), 0);
    }

    /**
     * Increment half-open calls count.
     */
    private function incrementHalfOpenCalls(): void
    {
        $key = $this->getHalfOpenCallsKey();
        $count = Cache::get($key, 0) + 1;
        Cache::put($key, $count, 300); // 5 minutes TTL
    }

    /**
     * Reset half-open calls count.
     */
    private function resetHalfOpenCalls(): void
    {
        Cache::forget($this->getHalfOpenCallsKey());
    }

    /**
     * Get cache keys.
     */
    private function getStateKey(): string
    {
        return "circuit_breaker_state_{$this->serviceName}";
    }

    private function getFailureCountKey(): string
    {
        return "circuit_breaker_failures_{$this->serviceName}";
    }

    private function getLastFailureTimeKey(): string
    {
        return "circuit_breaker_last_failure_{$this->serviceName}";
    }

    private function getHalfOpenCallsKey(): string
    {
        return "circuit_breaker_half_open_calls_{$this->serviceName}";
    }

    /**
     * Get circuit breaker status.
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->serviceName,
            'state' => $this->getState(),
            'failure_count' => $this->getFailureCount(),
            'failure_threshold' => $this->failureThreshold,
            'last_failure_time' => $this->getLastFailureTime(),
            'half_open_calls' => $this->getHalfOpenCalls(),
        ];
    }

    /**
     * Manually reset circuit breaker.
     */
    public function reset(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetFailureCount();
        $this->resetHalfOpenCalls();
        Cache::forget($this->getLastFailureTimeKey());
        
        Log::info('Circuit breaker manually reset', [
            'service' => $this->serviceName
        ]);
    }
}