<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliateClawback;
use App\Models\AffiliateCommissionEvent;
use App\Models\AffiliatePayout;
use App\Models\AffiliatePayoutBatch;
use App\Models\AffiliateProfile;
use App\Models\AffiliateReconciliationIncident;
use App\Models\AffiliateTier;
use App\Models\AuditLog;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\RewardPolicyDecision;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AffiliateCommissionService
{
    private const SCALE = 18;

    public function __construct(
        private readonly RewardPolicyEngine $rewardPolicy,
        private readonly ExaPointService $exaPoints,
        private readonly RewardSecurityService $security,
    ) {
    }

    public function ensureProfile(User $user): AffiliateProfile
    {
        $tier = $this->defaultTier();

        return AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'affiliate_tier_id' => $tier->id,
                'status' => 'ACTIVE',
                'payout_asset' => (string) config('affiliate.default_payout_asset', 'EXAPOINT'),
                'approved_at' => now(),
            ],
        )->load('tier');
    }

    public function recordSettledEvent(int $referredUserId, string $product, string $eventType, string $sourceReference, string $grossRevenue, array $context = []): ?AffiliateCommissionEvent
    {
        $product = strtoupper($product);
        $eventType = strtoupper($eventType);
        $registry = (array) config("affiliate.commissionable_events.{$product}.{$eventType}", []);
        if (($registry['enabled'] ?? false) !== true) {
            return null;
        }
        if (($context['environment'] ?? 'production') === 'sandbox') {
            return null;
        }
        if (($context['settlement_status'] ?? 'SETTLED') !== 'SETTLED') {
            return null;
        }

        $referral = Referral::query()->where('referred_user_id', $referredUserId)->first();
        if (!$referral) {
            return null;
        }

        $affiliate = User::query()->findOrFail($referral->referrer_user_id);
        $profile = $this->ensureProfile($affiliate);
        if ($profile->status !== 'ACTIVE') {
            return null;
        }

        return DB::transaction(function () use ($affiliate, $context, $eventType, $grossRevenue, $product, $profile, $referral, $registry, $referredUserId, $sourceReference): ?AffiliateCommissionEvent {
            $existing = AffiliateCommissionEvent::query()
                ->where('product', $product)
                ->where('event_type', $eventType)
                ->where('source_reference', $sourceReference)
                ->where('affiliate_user_id', $affiliate->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $base = FinancialDecimal::normalize((string) ($context['commissionable_base'] ?? $grossRevenue), self::SCALE);
            if (FinancialDecimal::compare($base, '0', self::SCALE) <= 0) {
                return null;
            }

            $flags = $this->security->inspect($affiliate, 'affiliate_commission', $context);
            $status = $flags === [] ? 'PENDING' : 'HELD';
            $holdUntil = now()->addDays((int) ($registry['hold_days'] ?? config('affiliate.default_hold_days', 14)));

            $decision = $this->decideReward($affiliate, $product, $eventType, $base, $profile, $context);
            $amount = (string) $decision->reward_amount;
            if ($decision->status !== 'APPROVED') {
                $status = 'HELD';
                $flags[] = $decision->reason_code ?: 'REWARD_POLICY_NOT_APPROVED';
            }

            $commission = AffiliateCommissionEvent::query()->create([
                'event_uuid' => (string) Str::uuid(),
                'referral_id' => $referral->id,
                'affiliate_user_id' => $affiliate->id,
                'referred_user_id' => $referredUserId,
                'reward_policy_decision_id' => $decision->id,
                'product' => $product,
                'event_type' => $eventType,
                'source_reference' => $sourceReference,
                'gross_revenue' => FinancialDecimal::normalize($grossRevenue, self::SCALE),
                'commissionable_base' => $base,
                'commission_rate_bps' => (string) ($profile->tier?->commission_rate_bps ?? '0'),
                'commission_amount' => FinancialDecimal::normalize($amount, self::SCALE),
                'reward_asset' => (string) $decision->reward_asset,
                'status' => $status,
                'hold_until' => $holdUntil,
                'qualified_at' => $status === 'PENDING' ? now() : null,
                'policy_snapshot' => $decision->rule_snapshot,
                'metadata' => array_merge($context, ['risk_flags' => array_values(array_filter($flags))]),
            ]);

            ReferralReward::query()->create([
                'referrer_id' => $affiliate->id,
                'referred_user_id' => $referredUserId,
                'reward_amount' => FinancialDecimal::normalize($amount, 8),
                'reward_token' => (string) $decision->reward_asset,
                'activity_type' => strtolower($product . '_' . $eventType),
                'level' => 1,
                'status' => strtolower($status),
                'event_key' => $sourceReference,
                'metadata' => ['affiliate_commission_event_uuid' => $commission->event_uuid],
                'approved_at' => $status === 'PENDING' ? now() : null,
            ]);

            $this->audit($affiliate->id, 'affiliate.commission.created', [
                'event_uuid' => $commission->event_uuid,
                'status' => $status,
                'product' => $product,
                'event_type' => $eventType,
                'source_reference' => $sourceReference,
            ]);

            return $commission;
        });
    }

    public function releaseMatureHolds(?int $affiliateUserId = null): int
    {
        return DB::transaction(function () use ($affiliateUserId): int {
            $query = AffiliateCommissionEvent::query()
                ->where('status', 'PENDING')
                ->where('hold_until', '<=', now())
                ->when($affiliateUserId, fn ($q) => $q->where('affiliate_user_id', $affiliateUserId))
                ->lockForUpdate();

            $count = 0;
            foreach ($query->get() as $commission) {
                $commission->forceFill([
                    'status' => 'AVAILABLE',
                    'available_at' => now(),
                ])->save();
                ReferralReward::query()
                    ->where('event_key', $commission->source_reference)
                    ->where('referrer_id', $commission->affiliate_user_id)
                    ->update(['status' => 'available']);
                $count++;
            }

            return $count;
        });
    }

    public function requestPayout(User $affiliate, string $amount, string $asset = 'EXAPOINT', ?string $idempotencyKey = null): AffiliatePayout
    {
        $profile = $this->ensureProfile($affiliate);
        if ($profile->status !== 'ACTIVE') {
            throw new RuntimeException('Affiliate account is not active.');
        }

        $asset = strtoupper($asset);
        if (!in_array($asset, (array) config('affiliate.payout_methods', ['EXAPOINT']), true)) {
            throw new RuntimeException('Requested affiliate payout method is not enabled.');
        }

        $amount = FinancialDecimal::normalize($amount, self::SCALE);
        if (FinancialDecimal::compare($amount, '0', self::SCALE) <= 0) {
            throw new RuntimeException('Payout amount must be greater than zero.');
        }

        $minimum = (string) ($profile->tier?->minimum_payout ?? config('affiliate.minimum_payout.EXAPOINT', '1'));
        if (FinancialDecimal::compare($amount, $minimum, self::SCALE) < 0) {
            throw new RuntimeException('Payout amount is below the configured minimum.');
        }

        $idempotencyKey = $idempotencyKey ?: 'affiliate:payout:' . $affiliate->id . ':' . $asset . ':' . $amount;

        return DB::transaction(function () use ($affiliate, $amount, $asset, $idempotencyKey): AffiliatePayout {
            $existing = AffiliatePayout::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $available = $this->availableAmount($affiliate->id, $asset);
            if (FinancialDecimal::compare($amount, $available, self::SCALE) > 0) {
                throw new RuntimeException('Requested payout exceeds available affiliate rewards.');
            }
            if (FinancialDecimal::compare($amount, $available, self::SCALE) !== 0) {
                throw new RuntimeException('Affiliate payout currently requires the full available amount to avoid partial payable ambiguity.');
            }

            $batch = AffiliatePayoutBatch::query()->create([
                'batch_uuid' => (string) Str::uuid(),
                'status' => 'COMPLETED',
                'asset' => $asset,
                'total_amount' => $amount,
                'item_count' => 1,
                'metadata' => ['source' => 'user_request'],
            ]);

            $payout = AffiliatePayout::query()->create([
                'payout_uuid' => (string) Str::uuid(),
                'affiliate_user_id' => $affiliate->id,
                'affiliate_payout_batch_id' => $batch->id,
                'method' => $asset,
                'asset' => $asset,
                'amount' => $amount,
                'status' => 'PAID',
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
                'approved_at' => now(),
                'paid_at' => now(),
                'metadata' => ['instrument' => $asset, 'financial_asset' => $asset !== 'EXAPOINT'],
            ]);

            $remaining = $amount;
            AffiliateCommissionEvent::query()
                ->where('affiliate_user_id', $affiliate->id)
                ->where('reward_asset', $asset)
                ->where('status', 'AVAILABLE')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get()
                ->each(function (AffiliateCommissionEvent $commission) use (&$remaining, $payout): void {
                    if (FinancialDecimal::compare($remaining, '0', self::SCALE) <= 0) {
                        return;
                    }

                    $commissionAmount = (string) $commission->commission_amount;
                    if (FinancialDecimal::compare($remaining, $commissionAmount, self::SCALE) >= 0) {
                        $commission->forceFill([
                            'status' => 'PAID',
                            'paid_at' => now(),
                            'metadata' => array_merge($commission->metadata ?? [], ['affiliate_payout_uuid' => $payout->payout_uuid]),
                        ])->save();
                        ReferralReward::query()
                            ->where('event_key', $commission->source_reference)
                            ->where('referrer_id', $commission->affiliate_user_id)
                            ->update(['status' => 'paid']);
                        $remaining = FinancialDecimal::sub($remaining, $commissionAmount, self::SCALE);
                        return;
                    }

                    $commission->forceFill([
                        'metadata' => array_merge($commission->metadata ?? [], [
                            'partial_payout_uuid' => $payout->payout_uuid,
                            'partial_payout_amount' => $remaining,
                        ]),
                    ])->save();
                    $remaining = '0';
                });

            if ($asset === 'EXAPOINT') {
                $this->exaPoints->earn($affiliate->id, $amount, 'affiliate:payout:' . $payout->payout_uuid, 'Affiliate reward payout', [
                    'affiliate_payout_uuid' => $payout->payout_uuid,
                ]);
            }

            $this->audit($affiliate->id, 'affiliate.payout.paid', [
                'payout_uuid' => $payout->payout_uuid,
                'amount' => $amount,
                'asset' => $asset,
            ]);

            return $payout;
        });
    }

    public function reverse(string $product, string $eventType, string $sourceReference, string $reversalReference, string $reasonCode): ?AffiliateClawback
    {
        $commission = AffiliateCommissionEvent::query()
            ->where('product', strtoupper($product))
            ->where('event_type', strtoupper($eventType))
            ->where('source_reference', $sourceReference)
            ->first();
        if (!$commission) {
            return null;
        }

        return DB::transaction(function () use ($commission, $reasonCode, $reversalReference): AffiliateClawback {
            $commission = AffiliateCommissionEvent::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();
            $existing = AffiliateClawback::query()
                ->where('affiliate_commission_event_id', $commission->id)
                ->where('reversal_reference', $reversalReference)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            if (in_array($commission->status, ['PENDING', 'HELD', 'QUALIFIED', 'AVAILABLE'], true)) {
                $commission->forceFill(['status' => 'REVERSED'])->save();
                ReferralReward::query()
                    ->where('event_key', $commission->source_reference)
                    ->where('referrer_id', $commission->affiliate_user_id)
                    ->update(['status' => 'reversed']);
                $status = 'APPLIED';
            } else {
                $commission->forceFill(['status' => 'CLAWBACK_PENDING'])->save();
                $status = 'PENDING';
            }

            $clawback = AffiliateClawback::query()->create([
                'clawback_uuid' => (string) Str::uuid(),
                'affiliate_commission_event_id' => $commission->id,
                'affiliate_user_id' => $commission->affiliate_user_id,
                'reversal_reference' => $reversalReference,
                'amount' => $commission->commission_amount,
                'asset' => $commission->reward_asset,
                'reason_code' => strtoupper($reasonCode),
                'status' => $status,
                'metadata' => ['original_status' => $commission->getOriginal('status')],
            ]);

            $this->audit($commission->affiliate_user_id, 'affiliate.clawback.created', [
                'clawback_uuid' => $clawback->clawback_uuid,
                'status' => $status,
                'reason_code' => $reasonCode,
            ]);

            return $clawback;
        });
    }

    public function overview(User $user): array
    {
        $profile = $this->ensureProfile($user);
        $this->releaseMatureHolds($user->id);

        return [
            'profile' => [
                'affiliate_code' => $user->referral_code,
                'referral_link' => rtrim((string) config('referral.frontend_register_url'), '/') . '?ref=' . $user->referral_code,
                'status' => $profile->status,
                'tier_name' => $profile->tier?->name ?? 'Standard',
                'commission_rate_bps' => (string) ($profile->tier?->commission_rate_bps ?? '0'),
                'payout_asset' => $profile->payout_asset,
            ],
            'stats' => $this->stats($user->id),
            'funnel' => $this->funnel($user->id),
        ];
    }

    public function referrals(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return Referral::query()
            ->where('referrer_user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage)
            ->through(fn (Referral $referral): array => [
                'referral' => 'User #' . $referral->referred_user_id,
                'joined_date' => $referral->created_at,
                'status' => $this->qualifiedReferralStatus($referral),
                'eligible_product' => AffiliateCommissionEvent::query()->where('referral_id', $referral->id)->latest()->value('product') ?? '--',
                'commission_status' => AffiliateCommissionEvent::query()->where('referral_id', $referral->id)->latest()->value('status') ?? '--',
            ]);
    }

    public function earnings(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return AffiliateCommissionEvent::query()
            ->where('affiliate_user_id', $user->id)
            ->latest()
            ->paginate($perPage)
            ->through(fn (AffiliateCommissionEvent $event): array => [
                'date' => $event->created_at,
                'referral' => 'User #' . $event->referred_user_id,
                'product' => $event->product,
                'plan' => $event->metadata['plan'] ?? null,
                'purchase_amount' => $event->gross_revenue,
                'commission_amount' => $event->commission_amount,
                'status' => $event->status,
            ]);
    }

    public function payouts(User $user): array
    {
        return [
            'summary' => [
                'asset' => $this->ensureProfile($user)->payout_asset,
                'pending' => $this->sumByStatus($user->id, ['PENDING', 'HELD']),
                'withdrawable' => $this->availableAmount($user->id, 'EXAPOINT'),
                'paid' => $this->sumByStatus($user->id, ['PAID']),
            ],
            'items' => AffiliatePayout::query()->where('affiliate_user_id', $user->id)->latest()->limit(20)->get(),
        ];
    }

    public function tools(User $user): array
    {
        $profile = $this->ensureProfile($user);
        $link = rtrim((string) config('referral.frontend_register_url'), '/') . '?ref=' . $user->referral_code;

        return [
            'referral_code' => $user->referral_code,
            'referral_link' => $link,
            'tier' => $profile->tier?->name ?? 'Standard',
            'share_copy' => 'Join ExaEarn through my referral link. Rewards apply only to eligible, settled activity.',
            'disclosure' => 'ExaPoints are the active reward instrument. ExaToken distribution is disabled.',
        ];
    }

    public function reconcile(): array
    {
        $duplicates = AffiliateCommissionEvent::query()
            ->select('product', 'event_type', 'source_reference', 'affiliate_user_id')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('product', 'event_type', 'source_reference', 'affiliate_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $paidWithoutPayout = AffiliateCommissionEvent::query()
            ->where('status', 'PAID')
            ->whereDoesntHave('referral')
            ->count();

        $incidents = 0;
        if ($duplicates->isNotEmpty()) {
            AffiliateReconciliationIncident::query()->create([
                'incident_uuid' => (string) Str::uuid(),
                'type' => 'DUPLICATE_COMMISSION',
                'severity' => 'HIGH',
                'status' => 'OPEN',
                'evidence' => $duplicates->toArray(),
            ]);
            $incidents++;
        }
        if ($paidWithoutPayout > 0) {
            AffiliateReconciliationIncident::query()->create([
                'incident_uuid' => (string) Str::uuid(),
                'type' => 'PAID_WITHOUT_REFERRAL',
                'severity' => 'HIGH',
                'status' => 'OPEN',
                'evidence' => ['count' => $paidWithoutPayout],
            ]);
            $incidents++;
        }

        return [
            'status' => $incidents === 0 ? 'PASS' : 'FAIL',
            'incidents_created' => $incidents,
            'duplicates' => $duplicates->count(),
            'paid_without_referral' => $paidWithoutPayout,
        ];
    }

    public function accountClosureBlockers(int $userId): array
    {
        $openCommissions = AffiliateCommissionEvent::query()
            ->where('affiliate_user_id', $userId)
            ->whereIn('status', ['PENDING', 'HELD', 'AVAILABLE', 'CLAWBACK_PENDING'])
            ->count();
        $openPayouts = AffiliatePayout::query()
            ->where('affiliate_user_id', $userId)
            ->whereIn('status', ['PENDING', 'DELIVERING', 'RETRYING', 'PROCESSING'])
            ->count();
        $openClawbacks = AffiliateClawback::query()
            ->where('affiliate_user_id', $userId)
            ->whereIn('status', ['PENDING', 'PARTIALLY_APPLIED', 'DISPUTED'])
            ->count();

        $blockers = [];
        if ($openCommissions > 0) {
            $blockers[] = ['product' => 'affiliate', 'reason' => 'unresolved_affiliate_commissions', 'count' => $openCommissions];
        }
        if ($openPayouts > 0) {
            $blockers[] = ['product' => 'affiliate', 'reason' => 'affiliate_payout_in_progress', 'count' => $openPayouts];
        }
        if ($openClawbacks > 0) {
            $blockers[] = ['product' => 'affiliate', 'reason' => 'affiliate_clawback_open', 'count' => $openClawbacks];
        }

        return $blockers;
    }

    private function decideReward(User $affiliate, string $product, string $eventType, string $base, AffiliateProfile $profile, array $context): RewardPolicyDecision
    {
        try {
            return $this->rewardPolicy->decide($affiliate, array_merge($context, [
                'product' => 'AFFILIATE',
                'operation' => $product . '_' . $eventType,
                'amount' => $base,
                'vip_tier' => $profile->tier?->code,
            ]));
        } catch (\Throwable) {
            $tier = $profile->tier ?: $this->defaultTier();
            $amount = FinancialDecimal::div(FinancialDecimal::mul($base, (string) $tier->commission_rate_bps, self::SCALE), '10000', self::SCALE);

            return RewardPolicyDecision::query()->create([
                'decision_uuid' => (string) Str::uuid(),
                'user_id' => $affiliate->id,
                'product' => 'AFFILIATE',
                'operation' => $product . '_' . $eventType,
                'gross_amount' => $base,
                'reward_amount' => $amount,
                'reward_asset' => (string) config('affiliate.default_payout_asset', 'EXAPOINT'),
                'status' => 'APPROVED',
                'reason_code' => null,
                'context' => array_merge($context, ['fallback' => 'affiliate_tier_policy']),
                'rule_snapshot' => [
                    'source' => 'affiliate_tier',
                    'tier' => $tier->code,
                    'commission_rate_bps' => (string) $tier->commission_rate_bps,
                    'version' => 1,
                ],
                'decided_at' => now(),
            ]);
        }
    }

    private function defaultTier(): AffiliateTier
    {
        return AffiliateTier::query()->firstOrCreate(
            ['code' => 'STANDARD'],
            [
                'name' => 'Standard',
                'commission_rate_bps' => (string) config('affiliate.default_commission_rate_bps', '1000'),
                'minimum_payout' => (string) config('affiliate.minimum_payout.EXAPOINT', '1'),
                'payout_frequency' => 'MONTHLY',
                'eligible_products' => array_keys((array) config('affiliate.commissionable_events', [])),
                'qualification_rules' => ['manual_review' => false],
                'status' => 'ACTIVE',
            ],
        );
    }

    private function stats(int $userId): array
    {
        return [
            'total_clicks' => 0,
            'total_signups' => Referral::query()->where('referrer_user_id', $userId)->count(),
            'active_subscribers' => AffiliateCommissionEvent::query()->where('affiliate_user_id', $userId)->distinct('referred_user_id')->count('referred_user_id'),
            'lifetime_earnings' => $this->sumByStatus($userId, ['PENDING', 'HELD', 'AVAILABLE', 'PAID', 'CLAWBACK_PENDING']),
            'withdrawable_earnings' => $this->availableAmount($userId, 'EXAPOINT'),
            'conversion_rate' => 0,
            'pending_rewards' => $this->sumByStatus($userId, ['PENDING']),
            'held_rewards' => $this->sumByStatus($userId, ['HELD']),
            'paid_rewards' => $this->sumByStatus($userId, ['PAID']),
        ];
    }

    private function funnel(int $userId): array
    {
        return [
            'clicks' => 0,
            'signups' => Referral::query()->where('referrer_user_id', $userId)->count(),
            'eligible_users' => Referral::query()->where('referrer_user_id', $userId)->count(),
            'plan_purchases' => AffiliateCommissionEvent::query()->where('affiliate_user_id', $userId)->count(),
            'commission_earned' => $this->sumByStatus($userId, ['PENDING', 'HELD', 'AVAILABLE', 'PAID']),
        ];
    }

    private function availableAmount(int $userId, string $asset): string
    {
        return FinancialDecimal::normalize((string) AffiliateCommissionEvent::query()
            ->where('affiliate_user_id', $userId)
            ->where('reward_asset', $asset)
            ->where('status', 'AVAILABLE')
            ->sum('commission_amount'), self::SCALE);
    }

    private function sumByStatus(int $userId, array $statuses): string
    {
        return FinancialDecimal::normalize((string) AffiliateCommissionEvent::query()
            ->where('affiliate_user_id', $userId)
            ->whereIn('status', $statuses)
            ->sum('commission_amount'), self::SCALE);
    }

    private function qualifiedReferralStatus(Referral $referral): string
    {
        $latest = AffiliateCommissionEvent::query()->where('referral_id', $referral->id)->latest()->first();
        if (!$latest) {
            return 'REGISTERED';
        }

        return in_array($latest->status, ['AVAILABLE', 'PAID'], true) ? 'QUALIFIED' : $latest->status;
    }

    private function audit(int $userId, string $action, array $metadata): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
