<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CheckinFraudService
{
    public function deviceHash(Request $request): string
    {
        $fingerprint = (string) $request->header('X-Device-Fingerprint', '');

        if ($fingerprint === '') {
            $fingerprint = implode('|', [
                $request->userAgent(),
                $request->header('Accept-Language'),
                $request->header('Sec-Ch-Ua-Platform'),
            ]);
        }

        return hash('sha256', $fingerprint);
    }

    public function assertClaimAllowed(User $user, Request $request): array
    {
        $ip = (string) $request->ip();
        $deviceHash = $this->deviceHash($request);
        $today = now()->toDateString();

        if (DailyCheckin::query()->where('user_id', $user->id)->whereDate('checkin_date', $today)->exists()) {
            throw new RuntimeException('Daily reward already claimed.');
        }

        $cooldownKey = "checkin:cooldown:{$user->id}";
        if (Cache::get($cooldownKey)) {
            throw new RuntimeException('Reward claim cooldown is active. Try again shortly.');
        }
        Cache::put($cooldownKey, true, now()->addSeconds((int) config('checkin.limits.cooldown_seconds')));

        $ipAccounts = DailyCheckin::query()
            ->whereDate('checkin_date', $today)
            ->where('ip_address', $ip)
            ->distinct('user_id')
            ->count('user_id');

        $deviceAccounts = DailyCheckin::query()
            ->whereDate('checkin_date', $today)
            ->where('device_hash', $deviceHash)
            ->distinct('user_id')
            ->count('user_id');

        $score = 0;
        if ($ipAccounts >= (int) config('checkin.limits.accounts_per_ip')) {
            $score += 45;
        }
        if ($deviceAccounts >= (int) config('checkin.limits.accounts_per_device')) {
            $score += 45;
        }
        if ($this->looksLikeProxy($request)) {
            $score += 20;
        }

        if ($score >= 80) {
            throw new RuntimeException('Reward claim requires manual security review.');
        }

        $this->assertCaptcha($request);

        return [
            'ip_address' => $ip,
            'device_hash' => $deviceHash,
            'risk_score' => $score,
            'ip_accounts_today' => $ipAccounts,
            'device_accounts_today' => $deviceAccounts,
        ];
    }

    public function assertRedemptionAllowed(User $user, Request $request): array
    {
        $context = $this->assertClaimSurfaceAllowed($user, $request);

        if (($context['risk_score'] ?? 0) >= 60) {
            throw new RuntimeException('Redemption requires manual security review.');
        }

        return $context;
    }

    private function assertClaimSurfaceAllowed(User $user, Request $request): array
    {
        $deviceHash = $this->deviceHash($request);
        $score = $this->looksLikeProxy($request) ? 20 : 0;

        return [
            'ip_address' => (string) $request->ip(),
            'device_hash' => $deviceHash,
            'risk_score' => $score,
        ];
    }

    private function assertCaptcha(Request $request): void
    {
        if (! config('checkin.captcha_enabled')) {
            return;
        }

        $token = (string) $request->input('captcha_token', '');
        if ($token === '') {
            throw new RuntimeException('Captcha validation is required.');
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('checkin.captcha_secret'),
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! $response->ok() || ! $response->json('success')) {
            throw new RuntimeException('Captcha validation failed.');
        }
    }

    private function looksLikeProxy(Request $request): bool
    {
        return $request->headers->has('Via')
            || $request->headers->has('X-Forwarded-Host')
            || str_contains(strtolower((string) $request->userAgent()), 'bot');
    }
}
