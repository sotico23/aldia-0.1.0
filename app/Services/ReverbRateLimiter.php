<?php
/**
 * Reverb Rate Limiter - Handles rate limiting for real-time events
 */

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class ReverbRateLimiter
{
    /**
     * Configure rate limits
     */
    public function configure(): void
    {
        RateLimiter::for('reverb:notifications', function ($request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reverb:delivery', function ($request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reverb:chat', function ($request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reverb:presence', function ($request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Check if limit exceeded
     */
    public function checkLimit(string $key, int $maxAttempts = 60, int $decayMinutes = 1): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Increment attempts
     */
    public function increment(string $key, int $decayMinutes = 1): void
    {
        RateLimiter::hit($key, $decayMinutes * 60);
    }

    /**
     * Get remaining attempts
     */
    public function remaining(string $key, int $maxAttempts = 60): int
    {
        return RateLimiter::remaining($key, $maxAttempts);
    }

    /**
     * Clear attempts
     */
    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }
}