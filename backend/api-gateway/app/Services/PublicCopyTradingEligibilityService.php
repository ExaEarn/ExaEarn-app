<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyJurisdictionRule;
use App\Models\CopyMarketEligibility;
use App\Models\CopyTerm;
use App\Models\CopyTermAcceptance;
use App\Models\User;

class PublicCopyTradingEligibilityService
{
    public function __construct(private readonly CopyPublicModeService $mode)
    {
    }

    public function evaluate(User $user, array $context = []): array
    {
        $reasons = [];
        $limits = [];
        $country = strtoupper((string) ($context['country'] ?? data_get($user->preferences, 'country', 'NG')));
        $product = strtolower((string) ($context['product_scope'] ?? $context['product'] ?? 'spot'));
        $symbols = (array) ($context['allowed_symbols'] ?? $context['symbols'] ?? []);

        $mode = $this->mode->mode();
        if (in_array($mode, ['DISABLED', 'PAUSED', 'EMERGENCY'], true)) {
            $reasons[] = 'COPY_TRADING_MODE_' . $mode;
        }
        if (!$this->mode->allowsNewRisk()) {
            $reasons[] = 'NEW_COPY_RISK_DISABLED';
        }

        $jurisdiction = CopyJurisdictionRule::query()->where('country', $country)->first();
        if (!$jurisdiction || $jurisdiction->status !== 'ENABLED') {
            $reasons[] = 'JURISDICTION_NOT_ENABLED';
        } else {
            $limits['jurisdiction_max_leverage'] = $jurisdiction->max_leverage;
            if (in_array($product, ['spot', 'all'], true) && !in_array($jurisdiction->spot_copy_public, ['LIMITED', 'ENABLED'], true)) {
                $reasons[] = 'SPOT_COPY_NOT_AVAILABLE_IN_REGION';
            }
            if (in_array($product, ['futures', 'all'], true) && !in_array($jurisdiction->futures_copy_public, ['LIMITED', 'ENABLED'], true)) {
                $reasons[] = 'FUTURES_COPY_NOT_AVAILABLE_IN_REGION';
            }
        }

        if (in_array($product, ['spot', 'all'], true) && !in_array($this->mode->flag('SPOT_COPY_PUBLIC'), ['LIMITED', 'ENABLED'], true)) {
            $reasons[] = 'SPOT_COPY_PUBLIC_DISABLED';
        }
        if (in_array($product, ['futures', 'all'], true) && !in_array($this->mode->flag('FUTURES_COPY_PUBLIC'), ['LIMITED', 'ENABLED'], true)) {
            $reasons[] = 'FUTURES_COPY_PUBLIC_DISABLED';
        }
        if (in_array($product, ['futures', 'all'], true) && (int) ($user->kyc_level ?? 0) < (int) config('copy_trading.public.minimum_futures_kyc_level', 1)) {
            $reasons[] = 'FUTURES_COPY_REQUIRES_KYC';
        }
        if (!(bool) ($user->two_factor_enabled ?? false) && (bool) config('copy_trading.public.require_2fa', true)) {
            $reasons[] = 'ACCOUNT_SECURITY_2FA_REQUIRED';
        }

        foreach ($symbols as $symbol) {
            $normalized = strtoupper(str_replace('/', '', (string) $symbol));
            $market = CopyMarketEligibility::query()->where('symbol', $normalized)->first();
            if (!$market || $market->status !== 'ENABLED') {
                $reasons[] = 'COPY_MARKET_NOT_ENABLED:' . $normalized;
                continue;
            }
            if (in_array($product, ['spot', 'all'], true) && !$market->spot_copy_public_enabled) {
                $reasons[] = 'SPOT_COPY_MARKET_DISABLED:' . $normalized;
            }
            if (in_array($product, ['futures', 'all'], true) && !$market->futures_copy_public_enabled) {
                $reasons[] = 'FUTURES_COPY_MARKET_DISABLED:' . $normalized;
            }
            $limits['market_max_slippage_bps'][$normalized] = (string) $market->maximum_slippage_bps;
        }

        $missingTerms = $this->missingTerms($user, $product);
        foreach ($missingTerms as $term) {
            $reasons[] = 'TERMS_NOT_ACCEPTED:' . $term;
        }

        $status = empty($reasons) ? 'ALLOWED' : 'BLOCKED';
        if ($status === 'ALLOWED' && ($mode === 'LIMITED_PUBLIC' || $this->mode->flag('SPOT_COPY_PUBLIC') === 'LIMITED' || $this->mode->flag('FUTURES_COPY_PUBLIC') === 'LIMITED')) {
            $status = 'ALLOWED_WITH_LIMITS';
        }

        return [
            'status' => $status,
            'country' => $country,
            'mode' => $mode,
            'product_scope' => $product,
            'reasons' => array_values(array_unique($reasons)),
            'limits' => $limits,
        ];
    }

    private function missingTerms(User $user, string $product): array
    {
        $types = ['copy_trading_terms', 'risk_disclosure'];
        if (in_array($product, ['futures', 'all'], true)) {
            $types[] = 'futures_copy_disclosure';
        }
        if (in_array(app(CopyPublicModeService::class)->flag('PROFIT_SHARE_PUBLIC'), ['LIMITED', 'ENABLED'], true)) {
            $types[] = 'profit_share_terms';
        }

        $missing = [];
        foreach ($types as $type) {
            $version = CopyTerm::query()->where('type', $type)->where('status', 'ACTIVE')->latest('id')->value('version');
            if (!$version) {
                $missing[] = $type;
                continue;
            }
            $accepted = CopyTermAcceptance::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('version', $version)
                ->exists();
            if (!$accepted) {
                $missing[] = $type;
            }
        }

        return $missing;
    }
}
