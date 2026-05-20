<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FraudLog;
use App\Models\Giftcard;
use App\Models\GiftcardOrder;
use App\Models\LoginDevice;
use App\Models\SuspiciousUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RiskEngineService
{
    public function __construct(private readonly BlockchainService $blockchainService)
    {
    }

    public function buildPayload(User $user, GiftcardOrder $order, array $context = []): array
    {
        $recentFailedTransactions = $user->transactions()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $submissionFrequency = GiftcardOrder::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $latestDevice = LoginDevice::query()->where('user_id', $user->id)->latest()->first();
        $cardHash = isset($context['card_hash']) ? (string) $context['card_hash'] : null;
        $excludeGiftcardId = isset($context['exclude_giftcard_id']) ? (int) $context['exclude_giftcard_id'] : null;
        $cardHashMatch = $cardHash
            ? Giftcard::query()
                ->where('card_hash', $cardHash)
                ->when($excludeGiftcardId, fn ($query) => $query->where('id', '!=', $excludeGiftcardId))
                ->exists()
            : false;

        return [
            'user_id' => $user->id,
            'amount' => (float) $order->amount,
            'transaction_type' => $order->type,
            'account_age_days' => (int) $user->created_at?->diffInDays(now()),
            'total_transactions' => $user->transactions()->count(),
            'failed_transactions' => $recentFailedTransactions,
            'ip_address' => (string) ($context['ip_address'] ?? request()?->ip() ?? ''),
            'device_id' => (string) ($context['device_id'] ?? $latestDevice?->fingerprint_hash ?? ''),
            'geo_location' => (string) ($context['geo_location'] ?? 'unknown'),
            'is_vpn' => (bool) ($context['is_vpn'] ?? false),
            'submission_frequency' => $submissionFrequency,
            'card_hash_match' => $cardHashMatch,
            'verified_source' => (bool) ($context['verified_source'] ?? false),
            'payment_method' => (string) ($order->payment_method ?? ''),
        ];
    }

    public function analyze(User $user, GiftcardOrder $order, array $context = []): array
    {
        $payload = $this->buildPayload($user, $order, $context);
        $response = $this->blockchainService->analyzeGiftcardFraud($payload);

        $riskScore = (int) ($response['risk_score'] ?? 0);
        $riskLevel = (string) ($response['risk_level'] ?? 'LOW');
        $reason = (array) ($response['reason'] ?? []);

        FraudLog::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'reason' => $reason,
            'ip' => $payload['ip_address'] ?: null,
            'device' => $payload['device_id'] ?: null,
            'payload' => $payload,
        ]);

        if (in_array($riskLevel, ['MEDIUM', 'HIGH'], true)) {
            SuspiciousUser::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'risk_level' => $riskLevel,
                    'flag_count' => SuspiciousUser::query()->where('user_id', $user->id)->value('flag_count') + 1,
                    'status' => $riskLevel === 'HIGH' && (bool) config('giftcards.fraud.freeze_high_risk_users', false)
                        ? 'frozen'
                        : 'active',
                    'metadata' => ['last_reason' => $reason, 'last_order_id' => $order->id],
                ]
            );
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'reason' => $reason,
            'payload' => $payload,
        ];
    }

    public function shouldAutoProcess(int $riskScore): bool
    {
        return $riskScore <= (int) config('giftcards.fraud.low_risk_max_score', 39);
    }

    public function requiresAdminReview(int $riskScore): bool
    {
        return $riskScore > (int) config('giftcards.fraud.low_risk_max_score', 39);
    }

    public function hashCardCode(string $cardCode): string
    {
        return hash('sha256', trim($cardCode));
    }
}
