<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\AdaptiveLimitingService;
use App\Services\Security\BotDetectionService;
use App\Services\Security\CaptchaVerificationService;
use App\Services\Security\DeviceFingerprintService;
use App\Services\Security\IPBlockingService;
use App\Services\Security\RateLimitingService;
use App\Services\Security\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function __construct(
        private readonly RateLimitingService $rateLimitService,
        private readonly BotDetectionService $botDetectionService,
        private readonly IPBlockingService $ipBlockingService,
        private readonly DeviceFingerprintService $deviceFingerprintService,
        private readonly AdaptiveLimitingService $adaptiveService,
        private readonly CaptchaVerificationService $captchaService,
        private readonly SecurityEventLogger $eventLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security-ratelimit.enabled')) {
            return $next($request);
        }

        $ip = $request->ip() ?? '0.0.0.0';
        $userAgent = $request->header('User-Agent', 'unknown');
        $endpoint = $this->getEndpointKey($request);
        $userId = $request->user()?->id;

        // Identifier can be IP or user_id
        $identifier = $userId ? "user:{$userId}" : "ip:{$ip}";

        // Generate device fingerprint
        $fingerprint = $this->deviceFingerprintService->generateFingerprint([
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'accept_language' => $request->header('Accept-Language', 'unknown'),
            'accept_encoding' => $request->header('Accept-Encoding', 'unknown'),
            'timezone_offset' => $request->header('X-Client-TZ-Offset', 'unknown'),
            'screen_resolution' => $request->header('X-Client-Screen', 'unknown'),
        ]);

        // Record device for user
        if ($userId) {
            $this->deviceFingerprintService->registerDevice($userId, $fingerprint);
            $this->deviceFingerprintService->recordIP($userId, $ip);
        }

        // Analyze bot behavior
        $analysis = $this->botDetectionService->analyzeRequest(
            $identifier,
            $endpoint,
            $userAgent,
            $ip,
            $userId,
            ['has_frontend_signals' => $request->header('X-Frontend-Initialized') === 'true']
        );

        // Check rate limits
        $result = $this->rateLimitService->checkRateLimit(
            $endpoint,
            $identifier,
            $userAgent,
            $ip
        );

        if (!$result['allowed']) {
            $this->eventLogger->logRateLimitHit(
                $endpoint,
                $identifier,
                $analysis['risk_level'],
                $ip,
                $userId
            );

            return response()->json([
                'message' => $result['message'],
                'retry_after' => $result['retry_after'] ?? 60,
            ], 429);
        }

        // Check if CAPTCHA is required
        if ($analysis['requires_captcha']) {
            $captchaChallenge = $this->captchaService->generateChallenge($identifier);

            return response()->json([
                'message' => 'CAPTCHA verification required',
                'captcha' => $captchaChallenge,
            ], 403);
        }

        // Log bot detection
        if (!empty($analysis['factors'])) {
            $this->eventLogger->logBotDetection(
                $identifier,
                $analysis['factors'],
                $analysis['risk_level'],
                $ip,
                $userId
            );
        }

        // Add rate limit headers to response
        return $this->addRateLimitHeaders(
            $next($request),
            $endpoint,
            $identifier
        );
    }

    /**
     * Extract endpoint key from request.
     */
    private function getEndpointKey(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        // Map route patterns to endpoint keys
        $patterns = [
            'api/auth/login' => 'auth.login',
            'api/auth/register' => 'auth.register',
            'api/auth/password' => 'auth.password_reset',
            'api/giftcard/purchase' => 'giftcard.purchase',
            'api/giftcard/sell' => 'giftcard.sell',
            'api/wallet/withdraw' => 'wallet.withdraw',
            'api/buy' => 'buy.token',
            'api/trade' => 'trade.execute',
        ];

        foreach ($patterns as $pattern => $key) {
            if (stripos($path, $pattern) !== false) {
                return $key;
            }
        }

        return 'default';
    }

    /**
     * Add rate limit headers to response.
     */
    private function addRateLimitHeaders(Response $response, string $endpoint, string $identifier): Response
    {
        $limit = config('security-ratelimit.limits.' . $endpoint . '.default', 60);
        $remaining = $this->rateLimitService->getRemainingRequests($endpoint, $identifier);
        $window = config('security-ratelimit.window', 60);

        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $limit);
            $response->header('X-RateLimit-Remaining', max(0, $remaining));
            $response->header('X-RateLimit-Reset', now()->addSeconds($window)->timestamp);
        }

        return $response;
    }
}
