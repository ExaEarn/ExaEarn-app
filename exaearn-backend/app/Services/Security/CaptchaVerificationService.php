<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptchaVerificationService
{
    /**
     * Verify CAPTCHA token with provider.
     */
    public function verify(string $token, ?string $remoteIP = null): array
    {
        $provider = config('security-ratelimit.captcha.provider', 'google');

        return match ($provider) {
            'google' => $this->verifyGoogle($token, $remoteIP),
            'hcaptcha' => $this->verifyHCaptcha($token, $remoteIP),
            default => ['success' => false, 'message' => 'Unknown CAPTCHA provider'],
        };
    }

    /**
     * Verify Google reCAPTCHA token.
     */
    private function verifyGoogle(string $token, ?string $remoteIP): array
    {
        try {
            $secretKey = config('security-ratelimit.captcha.google.secret_key');
            if (!$secretKey) {
                return ['success' => false, 'message' => 'CAPTCHA not configured'];
            }

            $response = Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIP,
                ]
            );

            $data = $response->json();
            $threshold = config('security-ratelimit.captcha.google.threshold', 0.5);

            if (!($data['success'] ?? false)) {
                return ['success' => false, 'message' => 'CAPTCHA verification failed'];
            }

            $score = (float) ($data['score'] ?? 0);
            if ($score < $threshold) {
                return ['success' => false, 'message' => 'CAPTCHA score too low'];
            }

            return [
                'success' => true,
                'score' => $score,
                'action' => $data['action'] ?? null,
                'challenge_ts' => $data['challenge_ts'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Google CAPTCHA verification error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Verification service error'];
        }
    }

    /**
     * Verify hCaptcha token.
     */
    private function verifyHCaptcha(string $token, ?string $remoteIP): array
    {
        try {
            $secretKey = config('security-ratelimit.captcha.hcaptcha.secret_key');
            if (!$secretKey) {
                return ['success' => false, 'message' => 'CAPTCHA not configured'];
            }

            $response = Http::asForm()->post(
                'https://hcaptcha.com/siteverify',
                [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIP,
                ]
            );

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                return ['success' => false, 'message' => 'CAPTCHA verification failed'];
            }

            return [
                'success' => true,
                'challenge_ts' => $data['challenge_ts'] ?? null,
                'hostname' => $data['hostname'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('hCaptcha verification error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Verification service error'];
        }
    }

    /**
     * Check if CAPTCHA should be triggered.
     */
    public function shouldTrigger(string $identifier, string $endpoint, string $riskLevel): bool
    {
        if (!config('security-ratelimit.captcha.enabled')) {
            return false;
        }

        $triggerOn = config('security-ratelimit.captcha.trigger_on', []);

        if ($riskLevel === 'suspicious' && ($triggerOn['suspicious_login'] ?? false)) {
            return true;
        }

        if ($riskLevel === 'blocked' && ($triggerOn['bot_detection'] ?? false)) {
            return true;
        }

        return false;
    }

    /**
     * Generate CAPTCHA challenge for frontend.
     */
    public function generateChallenge(string $identifier): array
    {
        $challengeId = uniqid('captcha_', true);
        Cache::put("captcha_challenge:{$challengeId}", [
            'identifier' => $identifier,
            'created_at' => now()->toIso8601String(),
            'verified' => false,
        ], 300); // 5 minutes

        $provider = config('security-ratelimit.captcha.provider', 'google');
        $siteKey = match ($provider) {
            'google' => config('security-ratelimit.captcha.google.site_key'),
            'hcaptcha' => config('security-ratelimit.captcha.hcaptcha.site_key'),
            default => '',
        };

        return [
            'challenge_id' => $challengeId,
            'provider' => $provider,
            'site_key' => $siteKey,
            'expires_in' => 300,
        ];
    }

    /**
     * Verify and mark CAPTCHA challenge as completed.
     */
    public function completeChallengeVerification(string $challengeId): bool
    {
        $key = "captcha_challenge:{$challengeId}";
        $challenge = Cache::get($key);

        if (!$challenge) {
            return false;
        }

        $challenge['verified'] = true;
        $challenge['verified_at'] = now()->toIso8601String();
        Cache::put($key, $challenge, 300);

        return true;
    }

    /**
     * Check if CAPTCHA challenge has been verified.
     */
    public function isChallengeVerified(string $challengeId): bool
    {
        $key = "captcha_challenge:{$challengeId}";
        $challenge = Cache::get($key);

        return $challenge && ($challenge['verified'] ?? false);
    }
}
