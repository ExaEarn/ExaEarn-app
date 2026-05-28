<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

class RateLimiterService
{
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    public function hit(string $key, int $decaySeconds): int
    {
        return RateLimiter::hit($key, $decaySeconds);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    public function assertWithinLimit(string $key, int $maxAttempts, int $decaySeconds, string $message): void
    {
        if ($this->tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            throw new \RuntimeException($message . ' Retry in ' . $this->availableIn($key) . ' seconds.');
        }

        $this->hit($key, $decaySeconds);
    }
}
