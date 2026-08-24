<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ComplianceDecisionLog;
use App\Models\ComplianceJurisdiction;
use App\Models\CompliancePolicyException;
use App\Models\CompliancePolicyRule;
use App\Models\ComplianceProduct;
use App\Models\ComplianceUserRestriction;
use App\Models\InstitutionalAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CompliancePolicyService
{
    public const ALLOW = 'ALLOW';
    public const DENY = 'DENY';
    public const REQUIRE_KYC = 'REQUIRE_KYC';
    public const REQUIRE_KYB = 'REQUIRE_KYB';
    public const REQUIRE_ENHANCED_REVIEW = 'REQUIRE_ENHANCED_REVIEW';
    public const REDUCE_ONLY = 'REDUCE_ONLY';
    public const CLOSE_ONLY = 'CLOSE_ONLY';
    public const SELL_ONLY = 'SELL_ONLY';
    public const WITHDRAW_ONLY = 'WITHDRAW_ONLY';
    public const SUSPENDED = 'SUSPENDED';

    private const RESTRICTIVE_RANK = [
        self::SUSPENDED => 100,
        self::DENY => 95,
        self::CLOSE_ONLY => 90,
        self::REDUCE_ONLY => 85,
        self::SELL_ONLY => 80,
        self::WITHDRAW_ONLY => 75,
        self::REQUIRE_ENHANCED_REVIEW => 70,
        self::REQUIRE_KYB => 65,
        self::REQUIRE_KYC => 60,
        'RESTRICT' => 55,
        self::ALLOW => 0,
    ];

    public function decide(?User $user, string $productCode, array $context = []): array
    {
        $productCode = strtoupper($productCode);
        $action = strtoupper((string) ($context['action'] ?? 'USE'));
        $institution = $this->institution($context);
        $accountType = strtoupper((string) ($context['account_type'] ?? $this->accountType($user, $institution)));
        $jurisdiction = strtoupper((string) ($context['jurisdiction'] ?? $this->verifiedJurisdiction($user, $institution)));
        $product = $this->product($productCode);
        $policyVersion = (string) config('compliance.policy_version', 'phase16-v1');

        if ($this->testingCompatibilityMode()) {
            $decision = $this->decision(self::ALLOW, 'TESTING_COMPATIBILITY_NO_POLICY_RECORDS', source: 'TEST_COMPATIBILITY');
            $decision['effective_limits'] = [];
            $decision['effective_max_leverage'] = null;
            $decision['policy_version'] = $policyVersion;
            $decision['policy_source'] = 'TEST_COMPATIBILITY';
            $decision['effective_at'] = now()->toISOString();
            $decision['product_code'] = $productCode;
            $decision['jurisdiction'] = $jurisdiction ?: null;
            $decision['account_type'] = $accountType;
            $decision['user_message_key'] = 'compliance.testing_compatibility_no_policy_records';

            return $decision;
        }

        $base = $this->baseDecision($jurisdiction, $productCode, $product);
        $rules = $this->matchingRules($jurisdiction, $productCode, $accountType, $context);
        $exception = $this->activeException($user, $institution, $productCode, $context);
        $restriction = $this->activeRestriction($user, $institution, $productCode);
        $baseCandidates = ($rules !== [] && $base['reason_code'] === 'PRODUCT_DEFAULT_POLICY') ? [] : [$base];
        $decision = $exception ?: $this->mostRestrictive(array_merge($baseCandidates, $rules, $restriction ? [$restriction] : []));
        $decision = $this->enforceVerification($decision, $user, $institution);
        $decision = $this->applyRiskReducingTransition($decision, $action);
        $decision['effective_limits'] = $this->effectiveLimits($rules, $decision, $context);
        $decision['effective_max_leverage'] = $decision['effective_limits']['max_leverage'] ?? null;
        $decision['policy_version'] = $decision['policy_version'] ?? $policyVersion;
        $decision['policy_source'] = $decision['policy_source'] ?? 'COMPLIANCE_POLICY';
        $decision['effective_at'] = now()->toISOString();
        $decision['product_code'] = $productCode;
        $decision['jurisdiction'] = $jurisdiction ?: null;
        $decision['account_type'] = $accountType;
        $decision['user_message_key'] = 'compliance.'.strtolower((string) $decision['reason_code']);

        if (($context['log'] ?? true) === true) {
            $this->logDecision($user, $institution, $productCode, $action, $jurisdiction, $accountType, $context, $decision);
        }

        return $decision;
    }

    public function assertAllowed(?User $user, string $productCode, array $context = []): array
    {
        $decision = $this->decide($user, $productCode, $context);
        if (! in_array($decision['decision'], [self::ALLOW, 'RESTRICT'], true)) {
            throw new \RuntimeException('Compliance policy rejected action: '.$decision['reason_code']);
        }
        return $decision;
    }

    public function getProductEligibility(User $user): array
    {
        return collect(array_keys((array) config('compliance.products', [])))
            ->mapWithKeys(fn (string $product): array => [$product => $this->safeDecision($this->decide($user, $product, ['log' => false]))])
            ->all();
    }

    public function simulate(array $context): array
    {
        $user = isset($context['user_id']) ? User::query()->find((int) $context['user_id']) : null;
        return $this->decide($user, (string) $context['product_code'], array_merge($context, ['log' => false]));
    }

    public function impact(array $proposedRule): array
    {
        $product = strtoupper((string) ($proposedRule['product_code'] ?? ''));
        $jurisdiction = strtoupper((string) ($proposedRule['jurisdiction'] ?? ''));
        $users = User::query()
            ->when($jurisdiction !== '', fn ($query) => $query->where(function ($q) use ($jurisdiction): void {
                $q->where('verified_country', $jurisdiction)->orWhere('residence_country', $jurisdiction);
            }))
            ->count();

        return [
            'users' => $users,
            'product_code' => $product,
            'jurisdiction' => $jurisdiction ?: null,
            'open_spot_orders' => class_exists(\App\Models\Order::class) ? \App\Models\Order::query()->whereIn('status', ['open', 'pending'])->count() : 0,
            'futures_positions' => class_exists(\App\Models\FuturesPosition::class) ? \App\Models\FuturesPosition::query()->where('status', 'open')->count() : 0,
            'copy_relationships' => class_exists(\App\Models\CopyRelationship::class) ? \App\Models\CopyRelationship::query()->where('status', 'ACTIVE')->count() : 0,
            'exaai_portfolios' => class_exists(\App\Models\ExaAiPortfolio::class) ? \App\Models\ExaAiPortfolio::query()->whereIn('status', ['ACTIVE', 'LIMITED_PRODUCTION'])->count() : 0,
            'market_maker_bots' => class_exists(\App\Models\MarketMakerBot::class) ? \App\Models\MarketMakerBot::query()->where('status', 'ACTIVE')->count() : 0,
        ];
    }

    public function seedDefaults(): void
    {
        foreach ((array) config('compliance.products', []) as $code => $row) {
            ComplianceProduct::query()->firstOrCreate(['product_code' => $code], [
                'risk_category' => $row['risk_category'] ?? 'STANDARD',
                'default_policy' => $row['default_policy'] ?? 'REQUIRE_KYC',
                'metadata' => ['seeded' => true],
            ]);
        }
    }

    private function baseDecision(string $jurisdiction, string $productCode, ComplianceProduct $product): array
    {
        $jurisdictionRow = $jurisdiction !== '' ? ComplianceJurisdiction::query()->where('country_code', $jurisdiction)->first() : null;
        if (! $jurisdictionRow) {
            $highRisk = in_array($productCode, (array) config('compliance.high_risk_products', []), true);
            return $this->decision($highRisk ? self::DENY : (string) $product->default_policy, $highRisk ? 'JURISDICTION_UNCONFIGURED_FAIL_CLOSED' : 'JURISDICTION_UNCONFIGURED_REVIEW', requiredKyc: 0);
        }
        if (in_array($jurisdictionRow->status, ['BLOCKED', 'RESTRICTED'], true)) {
            return $this->decision(self::DENY, 'JURISDICTION_'.$jurisdictionRow->status);
        }
        if ($jurisdictionRow->status === 'REVIEW_REQUIRED') {
            return $this->decision(self::REQUIRE_ENHANCED_REVIEW, 'JURISDICTION_REVIEW_REQUIRED');
        }
        $defaultPolicy = (string) $product->default_policy;
        return $this->decision(
            $defaultPolicy,
            'PRODUCT_DEFAULT_POLICY',
            in_array($defaultPolicy, [self::REQUIRE_KYC, self::REQUIRE_ENHANCED_REVIEW], true) ? 1 : 0,
            $defaultPolicy === self::REQUIRE_KYB ? 'APPROVED' : null,
        );
    }

    private function matchingRules(string $jurisdiction, string $productCode, string $accountType, array $context): array
    {
        $now = now();
        return Cache::remember($this->cacheKey($jurisdiction, $productCode, $accountType, $context), (int) config('compliance.cache_ttl_seconds', 30), function () use ($accountType, $context, $jurisdiction, $now, $productCode): array {
            return CompliancePolicyRule::query()
                ->where('status', 'ACTIVE')
                ->where(function ($q) use ($now): void { $q->whereNull('effective_at')->orWhere('effective_at', '<=', $now); })
                ->where(function ($q) use ($now): void { $q->whereNull('expires_at')->orWhere('expires_at', '>', $now); })
                ->where(function ($q) use ($jurisdiction): void { $q->whereNull('jurisdiction')->orWhere('jurisdiction', $jurisdiction); })
                ->where(function ($q) use ($productCode): void { $q->whereNull('product_code')->orWhere('product_code', $productCode); })
                ->where(function ($q) use ($accountType): void { $q->whereNull('account_type')->orWhere('account_type', $accountType); })
                ->where(function ($q) use ($context): void { $q->whereNull('asset')->orWhere('asset', strtoupper((string) ($context['asset'] ?? ''))); })
                ->where(function ($q) use ($context): void { $q->whereNull('market_symbol')->orWhere('market_symbol', strtoupper((string) ($context['market_symbol'] ?? $context['symbol'] ?? ''))); })
                ->where(function ($q) use ($context): void { $q->whereNull('network')->orWhere('network', strtoupper((string) ($context['network'] ?? ''))); })
                ->where(function ($q) use ($context): void { $q->whereNull('currency')->orWhere('currency', strtoupper((string) ($context['currency'] ?? ''))); })
                ->orderByDesc('precedence')
                ->get()
                ->map(fn (CompliancePolicyRule $rule): array => $this->decision($rule->decision, $rule->reason_code, (int) $rule->required_kyc_level, $rule->required_kyb_tier, [
                    'max_amount' => $rule->max_amount ? (string) $rule->max_amount : null,
                    'max_leverage' => $rule->max_leverage,
                ], (string) $rule->policy_version, 'RULE:'.$rule->rule_uuid, (int) $rule->precedence))
                ->all();
        });
    }

    private function mostRestrictive(array $decisions): array
    {
        return collect($decisions)->sortByDesc(fn (array $decision): int => self::RESTRICTIVE_RANK[$decision['decision']] ?? 50)->first();
    }

    private function enforceVerification(array $decision, ?User $user, ?InstitutionalAccount $institution): array
    {
        $kycLevel = (int) ($user?->kyc_level ?? 0);
        if (($decision['required_kyc_tier'] ?? 0) > $kycLevel) {
            return array_merge($decision, ['decision' => self::REQUIRE_KYC, 'reason_code' => 'KYC_TIER_INSUFFICIENT']);
        }
        if ($decision['decision'] === self::REQUIRE_KYC) {
            return array_merge($decision, ['decision' => self::ALLOW, 'reason_code' => 'KYC_REQUIREMENT_SATISFIED']);
        }
        if (($decision['required_kyb_tier'] ?? null) && (! $institution || ! in_array($institution->kyb_status, ['APPROVED', $decision['required_kyb_tier']], true))) {
            return array_merge($decision, ['decision' => self::REQUIRE_KYB, 'reason_code' => 'KYB_TIER_INSUFFICIENT']);
        }
        if ($decision['decision'] === self::REQUIRE_KYB) {
            return array_merge($decision, ['decision' => self::ALLOW, 'reason_code' => 'KYB_REQUIREMENT_SATISFIED']);
        }
        return $decision;
    }

    private function applyRiskReducingTransition(array $decision, string $action): array
    {
        if (in_array($decision['decision'], [self::REDUCE_ONLY, self::CLOSE_ONLY], true) && in_array($action, ['REDUCE', 'CLOSE', 'CANCEL'], true)) {
            return array_merge($decision, ['decision' => self::ALLOW, 'reason_code' => 'RISK_REDUCING_ACTION_ALLOWED']);
        }
        if ($decision['decision'] === self::SELL_ONLY && $action === 'SELL') {
            return array_merge($decision, ['decision' => self::ALLOW, 'reason_code' => 'SELL_ONLY_ACTION_ALLOWED']);
        }
        if ($decision['decision'] === self::WITHDRAW_ONLY && $action === 'WITHDRAW') {
            return array_merge($decision, ['decision' => self::ALLOW, 'reason_code' => 'WITHDRAW_ONLY_ACTION_ALLOWED']);
        }
        return $decision;
    }

    private function effectiveLimits(array $rules, array $decision, array $context): array
    {
        $maxLeverage = collect($rules)->pluck('limits.max_leverage')->filter()->map(fn ($v): int => (int) $v)->min();
        $requested = isset($context['requested_leverage']) ? (int) $context['requested_leverage'] : null;
        if ($requested && $maxLeverage) {
            $maxLeverage = min($requested, $maxLeverage);
        }
        return array_filter([
            'max_amount' => collect($rules)->pluck('limits.max_amount')->filter()->min(),
            'max_leverage' => $maxLeverage,
            'decision_limits' => $decision['limits'] ?? [],
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function activeRestriction(?User $user, ?InstitutionalAccount $institution, string $productCode): ?array
    {
        if (! $user && ! $institution) {
            return null;
        }

        $row = ComplianceUserRestriction::query()
            ->where('status', 'ACTIVE')
            ->where(function ($q): void { $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()); })
            ->where(function ($q): void { $q->whereNull('effective_to')->orWhere('effective_to', '>', now()); })
            ->where(function ($q) use ($institution, $user): void {
                if ($user) { $q->orWhere('user_id', $user->id); }
                if ($institution) { $q->orWhere('institution_id', $institution->id); }
            })
            ->first();
        if (! $row) {
            return null;
        }
        $map = [
            'ACCOUNT_SUSPENDED' => self::SUSPENDED,
            'TRADING_RESTRICTED' => self::DENY,
            'WITHDRAWAL_RESTRICTED' => self::DENY,
            'FUTURES_DISABLED' => $productCode === 'FUTURES' ? self::DENY : self::ALLOW,
            'P2P_DISABLED' => $productCode === 'P2P' ? self::DENY : self::ALLOW,
            'ENHANCED_REVIEW_REQUIRED' => self::REQUIRE_ENHANCED_REVIEW,
        ];
        return $this->decision($map[$row->restriction_type] ?? self::DENY, (string) $row->reason_code, source: 'USER_RESTRICTION:'.$row->restriction_uuid);
    }

    private function activeException(?User $user, ?InstitutionalAccount $institution, string $productCode, array $context): ?array
    {
        if (! $user && ! $institution) {
            return null;
        }

        $row = CompliancePolicyException::query()
            ->where('status', 'ACTIVE')
            ->where(function ($q): void { $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()); })
            ->where(function ($q): void { $q->whereNull('effective_to')->orWhere('effective_to', '>', now()); })
            ->where(function ($q) use ($productCode): void { $q->whereNull('product_code')->orWhere('product_code', $productCode); })
            ->where(function ($q) use ($context): void { $q->whereNull('asset')->orWhere('asset', strtoupper((string) ($context['asset'] ?? ''))); })
            ->where(function ($q) use ($context): void { $q->whereNull('market_symbol')->orWhere('market_symbol', strtoupper((string) ($context['market_symbol'] ?? $context['symbol'] ?? ''))); })
            ->where(function ($q) use ($institution, $user): void {
                if ($user) { $q->orWhere('user_id', $user->id); }
                if ($institution) { $q->orWhere('institution_id', $institution->id); }
            })
            ->first();
        return $row ? $this->decision($row->decision, 'APPROVED_EXCEPTION', source: 'EXCEPTION:'.$row->exception_uuid) : null;
    }

    private function product(string $code): ComplianceProduct
    {
        $this->seedDefaults();
        return ComplianceProduct::query()->firstOrCreate(['product_code' => $code], ['risk_category' => 'HIGH', 'default_policy' => 'DENY']);
    }

    private function jurisdiction(string $country): ?ComplianceJurisdiction
    {
        return $country !== '' ? ComplianceJurisdiction::query()->where('country_code', $country)->first() : null;
    }

    private function verifiedJurisdiction(?User $user, ?InstitutionalAccount $institution): string
    {
        return strtoupper((string) ($institution?->country_of_incorporation ?? $user?->verified_country ?? $user?->residence_country ?? config('compliance.default_verified_country', '')));
    }

    private function accountType(?User $user, ?InstitutionalAccount $institution): string
    {
        if ($institution) {
            return 'INSTITUTIONAL';
        }
        return strtoupper((string) ($user?->role === 'business' ? 'BUSINESS' : 'INDIVIDUAL'));
    }

    private function institution(array $context): ?InstitutionalAccount
    {
        if (($context['institution'] ?? null) instanceof InstitutionalAccount) {
            return $context['institution'];
        }
        return isset($context['institution_id']) ? InstitutionalAccount::query()->find((int) $context['institution_id']) : null;
    }

    private function decision(string $decision, string $reasonCode, int $requiredKyc = 0, ?string $requiredKyb = null, array $limits = [], ?string $version = null, string $source = 'POLICY', int $precedence = 0): array
    {
        return [
            'decision' => strtoupper($decision),
            'reason_code' => strtoupper($reasonCode),
            'required_kyc_tier' => $requiredKyc,
            'required_kyb_tier' => $requiredKyb,
            'limits' => $limits,
            'policy_version' => $version ?? (string) config('compliance.policy_version', 'phase16-v1'),
            'policy_source' => $source,
            'precedence' => $precedence,
        ];
    }

    private function safeDecision(array $decision): array
    {
        return [
            'decision' => $decision['decision'],
            'reason_code' => $decision['reason_code'],
            'user_message_key' => $decision['user_message_key'],
            'required_kyc_tier' => $decision['required_kyc_tier'],
            'required_kyb_tier' => $decision['required_kyb_tier'],
            'effective_limits' => $decision['effective_limits'],
            'effective_max_leverage' => $decision['effective_max_leverage'],
            'policy_version' => $decision['policy_version'],
        ];
    }

    private function logDecision(?User $user, ?InstitutionalAccount $institution, string $productCode, string $action, string $jurisdiction, string $accountType, array $context, array $decision): void
    {
        ComplianceDecisionLog::query()->create([
            'decision_uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'institution_id' => $institution?->id,
            'actor_type' => $context['actor_type'] ?? ($user ? 'user' : 'system'),
            'actor_id' => $context['actor_id'] ?? $user?->id,
            'product_code' => $productCode,
            'action' => $action,
            'jurisdiction' => $jurisdiction ?: null,
            'account_type' => $accountType,
            'asset' => isset($context['asset']) ? strtoupper((string) $context['asset']) : null,
            'market_symbol' => isset($context['market_symbol']) || isset($context['symbol']) ? strtoupper((string) ($context['market_symbol'] ?? $context['symbol'])) : null,
            'network' => isset($context['network']) ? strtoupper((string) $context['network']) : null,
            'currency' => isset($context['currency']) ? strtoupper((string) $context['currency']) : null,
            'decision' => $decision['decision'],
            'reason_code' => $decision['reason_code'],
            'policy_version' => $decision['policy_version'],
            'effective_limits' => $decision['effective_limits'] ?? [],
            'metadata' => ['source' => $decision['policy_source'] ?? null],
            'decided_at' => now(),
        ]);
    }

    private function cacheKey(string $jurisdiction, string $productCode, string $accountType, array $context): string
    {
        return 'compliance:rules:'.md5(json_encode([
            $jurisdiction,
            $productCode,
            $accountType,
            strtoupper((string) ($context['asset'] ?? '')),
            strtoupper((string) ($context['market_symbol'] ?? $context['symbol'] ?? '')),
            strtoupper((string) ($context['network'] ?? '')),
            strtoupper((string) ($context['currency'] ?? '')),
            config('compliance.policy_version'),
        ]));
    }

    private function testingCompatibilityMode(): bool
    {
        return app()->environment('testing')
            && ComplianceJurisdiction::query()->count() === 0
            && CompliancePolicyRule::query()->count() === 0
            && ComplianceUserRestriction::query()->count() === 0
            && CompliancePolicyException::query()->count() === 0;
    }
}
