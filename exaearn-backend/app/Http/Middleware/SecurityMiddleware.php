<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RateLimiterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    public function __construct(private readonly RateLimiterService $rateLimiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isSensitiveRoute($request)) {
            return $next($request);
        }

        $this->enforceSensitiveApiRateLimit($request);

        if ((bool) config('security.api.signature_required', false)) {
            $this->validateSignature($request);
        }

        $this->validateNonce($request);

        return $next($request);
    }

    private function isSensitiveRoute(Request $request): bool
    {
        $patterns = (array) config('security.api.sensitive_patterns', []);
        foreach ($patterns as $pattern) {
            if ($request->is((string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function validateSignature(Request $request): void
    {
        $signature = (string) $request->header('X-EXA-SIGNATURE', '');
        $timestamp = (string) $request->header('X-EXA-TIMESTAMP', '');
        $secret = (string) config('security.api.signature_secret', '');

        if ($signature === '' || $timestamp === '' || $secret === '') {
            abort(response()->json(['message' => 'Invalid API signature.'], 403));
        }

        $ttl = (int) config('security.api.signature_ttl_seconds', 120);
        if (abs(time() - (int) $timestamp) > $ttl) {
            abort(response()->json(['message' => 'Expired API signature timestamp.'], 403));
        }

        $body = $request->getContent();
        $payload = $request->method() . '|' . $request->path() . '|' . $timestamp . '|' . $body;
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            abort(response()->json(['message' => 'Invalid API signature.'], 403));
        }
    }

    private function validateNonce(Request $request): void
    {
        $nonce = trim((string) $request->header('X-EXA-NONCE', ''));
        if ($nonce === '') {
            abort(response()->json(['message' => 'Missing request nonce.'], 422));
        }

        $identity = $request->user()?->id ?? $request->ip();
        $key = 'security:nonce:' . $identity . ':' . hash('sha256', $nonce);
        $nonceTtl = (int) config('security.api.nonce_ttl_seconds', 120);

        if ($this->rateLimiter->tooManyAttempts($key, 1, $nonceTtl)) {
            abort(response()->json(['message' => 'Replay request detected.'], 409));
        }

        $this->rateLimiter->hit($key, $nonceTtl);
    }

    private function enforceSensitiveApiRateLimit(Request $request): void
    {
        $max = (int) config('security.api.rate_limit_per_minute', 120);
        $decay = 60;

        $byIp = 'security:api:ip:' . $request->ip();
        $byIdentity = 'security:api:user:' . ($request->user()?->id ?? hash('sha256', (string) $request->ip()));

        if ($this->rateLimiter->tooManyAttempts($byIp, $max, $decay)
            || $this->rateLimiter->tooManyAttempts($byIdentity, $max, $decay)) {
            abort(response()->json([
                'message' => 'API rate limit exceeded.',
                'retry_in_seconds' => max(
                    $this->rateLimiter->availableIn($byIp),
                    $this->rateLimiter->availableIn($byIdentity)
                ),
            ], 429));
        }

        $this->rateLimiter->hit($byIp, $decay);
        $this->rateLimiter->hit($byIdentity, $decay);
    }
}

