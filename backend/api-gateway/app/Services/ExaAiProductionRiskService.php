<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiDecision;
use App\Models\ExaAiMarketEligibility;
use App\Models\ExaAiPortfolio;
use App\Models\ExaAiPublicSetting;
use App\Models\ExaAiTermAcceptance;
use Carbon\CarbonImmutable;

class ExaAiProductionRiskService
{
    private const SCALE = 8;

    public function __construct(
        private readonly ExaAiPositionSizingService $sizing,
        private readonly ExaAiEntitlementService $entitlements,
    )
    {
    }

    public function evaluate(ExaAiPortfolio $portfolio, array $payload): array
    {
        $setting = ExaAiPublicSetting::query()->where('key', 'global_controls')->first();
        $controls = $setting?->value ?? [];
        $mode = strtoupper((string) ($controls['state'] ?? (($controls['global_kill_switch'] ?? false) ? 'EMERGENCY' : 'NORMAL')));

        if (in_array($mode, ['NEW_RISK_DISABLED', 'REDUCE_ONLY', 'PAUSED', 'EMERGENCY'], true)) {
            return $this->reject('EXAAI_' . $mode, ['global_state' => $mode]);
        }

        if ($portfolio->status !== 'active') {
            return $this->reject('PORTFOLIO_NOT_ACTIVE', ['status' => $portfolio->status]);
        }

        $session = $portfolio->session;
        if (! $session || $session->status !== 'active') {
            return $this->reject('SESSION_NOT_ACTIVE', ['session_status' => $session?->status]);
        }

        if (! $session->subscription || $session->subscription->status !== 'active' || ($session->subscription->ends_at && $session->subscription->ends_at->isPast())) {
            return $this->reject('SUBSCRIPTION_NOT_ACTIVE', ['subscription_status' => $session->subscription?->status]);
        }

        $terms = ExaAiTermAcceptance::query()
            ->where('user_id', $portfolio->user_id)
            ->where('acceptance_scope', 'exaai_automated_trading')
            ->exists();
        if (! $terms) {
            return $this->reject('TERMS_NOT_ACCEPTED', []);
        }

        $symbol = strtoupper(str_replace('/', '', (string) ($payload['symbol'] ?? '')));
        $product = strtolower((string) ($payload['product'] ?? 'spot'));
        $effective = $this->entitlements->effectiveFor($portfolio->user, [
            'product' => $product,
            'action' => 'NEW_RISK',
            'strategy_code' => $portfolio->strategy?->code,
            'symbol' => $symbol,
            'requested_capital' => (string) ($payload['requested_notional'] ?? '0'),
            'requested_leverage' => (int) data_get($payload, 'leverage', data_get($portfolio->limits, 'leverage', 1)),
        ]);
        if (! $effective['allowed']) {
            return $this->reject('ENTITLEMENT_REJECTED', [
                'mode' => $effective['mode'],
                'reasons' => $effective['reasons'],
                'entitlements' => $effective['entitlements'],
            ]);
        }

        $eligibility = ExaAiMarketEligibility::query()
            ->where('symbol', $symbol)
            ->where('product', $product)
            ->first();

        if (! $eligibility || $eligibility->status !== 'enabled') {
            return $this->reject('MARKET_NOT_ELIGIBLE', ['symbol' => $symbol, 'product' => $product]);
        }

        $snapshot = $payload['market_snapshot'] ?? [];
        $updatedAt = CarbonImmutable::parse((string) ($snapshot['updated_at'] ?? now()->subYears(10)->toISOString()));
        $freshnessSeconds = (int) $eligibility->market_data_freshness_seconds;
        if ($updatedAt->lt(CarbonImmutable::now()->subSeconds($freshnessSeconds))) {
            return $this->reject('STALE_MARKET_DATA', [
                'updated_at' => $updatedAt->toISOString(),
                'freshness_seconds' => $freshnessSeconds,
            ]);
        }

        $maxExposure = $this->fmt((string) $eligibility->max_exposure);
        try {
            $sizing = $this->sizing->size($portfolio, $payload, $maxExposure);
        } catch (\RuntimeException $exception) {
            return $this->reject('INVALID_POSITION_SIZE', ['error' => $exception->getMessage()]);
        }

        if ($this->compare($sizing['approved_notional'], '0') <= 0) {
            return $this->reject('INSUFFICIENT_EXAAI_CAPITAL', $sizing);
        }

        $confidence = (int) ($payload['confidence'] ?? 0);
        $minConfidence = (int) ($session->constraints['min_signal_confidence'] ?? 60);
        if ($confidence < $minConfidence) {
            return $this->reject('LOW_SIGNAL_CONFIDENCE', [
                'confidence' => $confidence,
                'min_confidence' => $minConfidence,
            ]);
        }

        $referencePrice = $this->fmt((string) ($payload['reference_price'] ?? $snapshot['last_price'] ?? '0'));
        if ($this->compare($referencePrice, '0') <= 0) {
            return $this->reject('INVALID_REFERENCE_PRICE', []);
        }

        return [
            'approved' => true,
            'reason_code' => 'APPROVED',
            'approved_notional' => $sizing['approved_notional'],
            'quantity' => $sizing['quantity'],
            'risk_snapshot' => [
                'available_before' => $sizing['available_before'],
                'requested_notional' => $sizing['requested_notional'],
                'approved_notional' => $sizing['approved_notional'],
                'portfolio_cap' => $sizing['portfolio_cap'],
                'market_max_exposure' => $maxExposure,
                'confidence' => $confidence,
                'min_confidence' => $minConfidence,
                'reference_price' => $referencePrice,
                'market_data_updated_at' => $updatedAt->toISOString(),
            ],
        ];
    }

    private function reject(string $reason, array $snapshot): array
    {
        return [
            'approved' => false,
            'reason_code' => $reason,
            'approved_notional' => '0.00000000',
            'quantity' => '0.00000000',
            'risk_snapshot' => $snapshot,
        ];
    }

    private function fmt(string $value): string
    {
        if (! function_exists('bcadd')) {
            throw new \RuntimeException('BCMath is required for ExaAI financial calculations.');
        }

        return bcadd(trim($value), '0', self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }
}
