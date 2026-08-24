<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\ExaAiAllocation;
use App\Models\ExaAiAuditLog;
use App\Models\ExaAiCapitalAllocation;
use App\Models\ExaAiOrder;
use App\Models\ExaAiPlan;
use App\Models\ExaAiSession;
use App\Models\ExaAiStrategyDefinition;
use App\Models\ExaAiStrategyVersion;
use App\Models\ExaAiSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ExaAiService
{
    private const SCALE = 8;

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly UnifiedTradingAccountService $accounts,
        private readonly ReferralService $referrals,
        private readonly NotificationService $notifications,
        private readonly ExaAiEntitlementService $entitlements,
    ) {
    }

    public function getOverview(User $user): array
    {
        $subscription = $this->getCurrentSubscription($user);
        $session = $this->getCurrentSession($user);
        $activeAllocation = ExaAiCapitalAllocation::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        $orders = ExaAiOrder::query()->where('user_id', $user->id);
        $completedOrders = (clone $orders)->where('status', 'closed')->get();
        $openOrders = (clone $orders)->whereIn('status', ['pending', 'open'])->get();

        $realized = $this->sumCollection($completedOrders, 'realized_pnl');
        $unrealized = $this->sumCollection($openOrders, 'unrealized_pnl');
        $fees = $this->sumCollection($completedOrders, 'fees');
        $wins = $completedOrders->filter(fn (ExaAiOrder $order): bool => $this->compare((string) $order->realized_pnl, '0') > 0)->count();
        $losses = $completedOrders->filter(fn (ExaAiOrder $order): bool => $this->compare((string) $order->realized_pnl, '0') < 0)->count();
        $tradeCount = $completedOrders->count();
        $winRate = $tradeCount > 0 ? round(($wins / $tradeCount) * 100, 2) : null;
        $maxDrawdown = $this->calculateMaxDrawdown($completedOrders);
        $todayPnL = $this->sumCollection(
            $completedOrders->filter(fn (ExaAiOrder $order): bool => optional($order->closed_at)?->isToday() === true),
            'realized_pnl'
        );

        return [
            'status' => [
                'subscription_status' => $subscription?->status ?? 'inactive',
                'session_status' => $session?->status ?? 'stopped',
                'current_plan' => $subscription?->plan?->name,
                'current_strategy' => $session?->strategy?->name,
                'risk_level' => $session?->risk_level,
                'mode' => $session?->mode ?? 'live',
            ],
            'effective_permission' => $this->safeEntitlements($user),
            'capital' => [
                'allocated_capital' => $activeAllocation?->amount ? (string) $activeAllocation->amount : '0',
                'available_exaai_capital' => $activeAllocation?->available_amount ? (string) $activeAllocation->available_amount : '0',
                'reserved_exaai_capital' => $activeAllocation?->reserved_amount ? (string) $activeAllocation->reserved_amount : '0',
            ],
            'performance' => [
                'total_pnl' => $this->add($realized, $unrealized),
                'realized_pnl' => $realized,
                'unrealized_pnl' => $unrealized,
                'today_pnl' => $todayPnL,
                'fees' => $fees,
                'completed_trades' => $tradeCount,
                'open_positions' => $openOrders->count(),
                'win_rate' => $winRate,
                'winning_trades' => $wins,
                'losing_trades' => $losses,
                'max_drawdown_percent' => $maxDrawdown,
                'not_enough_history' => $tradeCount < 3,
            ],
            'next' => [
                'renewal_at' => $subscription?->renewal_at?->toISOString(),
                'session_end_at' => $session?->ends_at?->toISOString(),
                'last_action' => $openOrders->sortByDesc('updated_at')->first()?->pair,
            ],
        ];
    }

    public function getPlans(): Collection
    {
        return ExaAiPlan::query()
            ->where('is_active', true)
            ->orderByRaw("case code when 'starter' then 1 when 'pro' then 2 when 'elite' then 3 else 99 end")
            ->get();
    }

    public function getCurrentSubscription(User $user): ?ExaAiSubscription
    {
        return ExaAiSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due'])
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    public function subscribe(User $user, array $payload): ExaAiSubscription
    {
        $plan = ExaAiPlan::query()
            ->where('code', strtolower((string) ($payload['plan_code'] ?? '')))
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            throw new RuntimeException('Selected ExaAI plan is unavailable.');
        }

        $billingCycle = strtolower((string) ($payload['billing_cycle'] ?? 'monthly'));
        if (! in_array($billingCycle, ['monthly', 'annual'], true)) {
            throw new RuntimeException('Unsupported billing cycle.');
        }

        $amount = $billingCycle === 'annual' && $plan->annual_price !== null
            ? (string) $plan->annual_price
            : (string) $plan->price;

        if ($this->compare($amount, '0') <= 0) {
            throw new RuntimeException('Selected ExaAI plan does not have a valid price.');
        }

        return DB::transaction(function () use ($amount, $billingCycle, $plan, $user): ExaAiSubscription {
            ExaAiSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            $reference = 'EXAAI-SUB-' . strtoupper(Str::random(12));
            $this->transactions->recordDebit(
                $user->id,
                TransactionType::SubscriptionPayment,
                (string) $plan->settlement_asset,
                $amount,
                $reference,
                [
                    'source' => 'exaai_subscription',
                    'plan_code' => $plan->code,
                    'billing_cycle' => $billingCycle,
                ]
            );

            $startsAt = now();
            $endsAt = $billingCycle === 'annual' ? now()->addYear() : now()->addMonth();

            $subscription = ExaAiSubscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'settlement_asset' => $plan->settlement_asset,
                'amount' => $amount,
                'transaction_reference' => $reference,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'renewal_at' => $endsAt,
                'metadata' => [
                    'source' => 'exaai',
                    'execution_tier' => $plan->execution_tier,
                    'analytics_level' => $plan->analytics_level,
                ],
            ]);

            if ((bool) $plan->affiliate_eligible) {
                $this->referrals->queueQualifiedActivity($user->id, 'subscription_purchase', [
                    'source' => 'exaai',
                    'plan_code' => $plan->code,
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,
                ]);
            }

            $this->notifications->create(
                $user,
                'exaai_subscription',
                'ExaAI plan activated',
                sprintf('%s plan is active until %s.', $plan->name, $endsAt->toDateString()),
                channels: ['in_app'],
                data: ['page' => 'exaai']
            );

            $this->audit($user->id, null, 'subscription.activated', 'ExaAI subscription activated.', [
                'plan_code' => $plan->code,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'reference' => $reference,
            ]);

            return $subscription->load('plan');
        });
    }

    public function strategiesFor(User $user): Collection
    {
        $subscription = $this->getCurrentSubscription($user);
        $allowedCodes = $subscription ? $this->entitlements->effectiveFor($user, ['log_compliance' => false])['entitlements']['allowed_strategies'] : [];

        return ExaAiStrategyDefinition::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (ExaAiStrategyDefinition $strategy) use ($allowedCodes): bool {
                if ($allowedCodes === [] || $allowedCodes === null) {
                    return false;
                }

                return in_array($strategy->code, $allowedCodes, true);
            })
            ->values();
    }

    public function createAllocation(User $user, array $payload): ExaAiCapitalAllocation
    {
        $subscription = $this->getCurrentSubscription($user);
        if (! $subscription) {
            throw new RuntimeException('An active ExaAI plan is required before allocating capital.');
        }

        $asset = strtoupper((string) ($payload['asset'] ?? 'USDT'));
        $amount = $this->fmt((string) ($payload['amount'] ?? '0'));
        if ($this->compare($amount, '0') <= 0) {
            throw new RuntimeException('Allocation amount must be greater than zero.');
        }

        $balances = $this->accounts->getUnifiedTradingBalances($user->id);
        $summary = $balances[$asset] ?? null;
        if (! $summary) {
            throw new RuntimeException('Selected asset is unavailable in Unified Trading.');
        }

        $activeAllocated = (string) ExaAiCapitalAllocation::query()
            ->where('user_id', $user->id)
            ->where('asset', $asset)
            ->where('status', 'active')
            ->sum('available_amount');

        $transferable = $this->fmt((string) ($summary['transferable'] ?? '0'));
        $remaining = $this->sub($transferable, $activeAllocated);

        if ($this->compare($amount, $remaining) > 0) {
            throw new RuntimeException('Allocation exceeds available Unified Trading capital.');
        }

        $effective = $this->entitlements->assertCanUse($user, [
            'product' => 'spot',
            'action' => 'ALLOCATE',
            'requested_capital' => $amount,
        ]);

        if ($this->compare($amount, (string) $effective['entitlements']['maximum_ai_capital']) > 0) {
            throw new RuntimeException('Allocation exceeds the current ExaAI plan capital limit.');
        }

        $allocation = ExaAiCapitalAllocation::query()->create([
            'user_id' => $user->id,
            'asset' => $asset,
            'amount' => $amount,
            'available_amount' => $amount,
            'reserved_amount' => '0',
            'status' => 'active',
            'reference' => 'EXAAI-ALLOC-' . strtoupper(Str::random(12)),
            'metadata' => [
                'source' => 'unified_trading',
                'transferable_snapshot' => $transferable,
            ],
        ]);

        $this->audit($user->id, null, 'allocation.created', 'ExaAI capital allocation created.', [
            'allocation_id' => $allocation->id,
            'asset' => $asset,
            'amount' => $amount,
        ]);

        return $allocation;
    }

    public function allocations(User $user): Collection
    {
        return ExaAiCapitalAllocation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();
    }

    public function activeAllocation(User $user): ?ExaAiCapitalAllocation
    {
        return ExaAiCapitalAllocation::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    public function startSession(User $user, array $payload): ExaAiSession
    {
        $subscription = $this->getCurrentSubscription($user);
        if (! $subscription) {
            throw new RuntimeException('An active ExaAI plan is required before starting automation.');
        }

        $allocation = ExaAiCapitalAllocation::query()
            ->where('user_id', $user->id)
            ->whereKey((int) ($payload['allocation_id'] ?? 0))
            ->where('status', 'active')
            ->first();

        if (! $allocation) {
            throw new RuntimeException('Selected ExaAI capital allocation is unavailable.');
        }

        $strategy = ExaAiStrategyDefinition::query()
            ->whereKey((int) ($payload['strategy_id'] ?? 0))
            ->where('is_active', true)
            ->first();

        if (! $strategy) {
            throw new RuntimeException('Selected ExaAI strategy is unavailable.');
        }

        $mode = $this->normalizeMode((string) ($payload['mode'] ?? 'paper'));
        $effective = $this->entitlements->assertCanUse($user, [
            'product' => $strategy->supports_futures ? 'futures' : 'spot',
            'action' => 'START_SESSION',
            'strategy_code' => $strategy->code,
            'requested_capital' => (string) $allocation->amount,
            'requested_leverage' => (int) data_get($payload, 'constraints.leverage', 1),
        ]);

        if (! in_array($strategy->code, $effective['entitlements']['allowed_strategies'] ?? [], true)) {
            throw new RuntimeException('Current plan does not permit the selected strategy.');
        }

        if ($mode === 'live' && ! (bool) ($payload['live_authorization'] ?? false)) {
            throw new RuntimeException('LIVE ExaAI sessions require explicit user authorization.');
        }

        $currentVersion = $strategy->versions()->where('is_current', true)->first();
        if (! $currentVersion) {
            throw new RuntimeException('Selected strategy has no active version.');
        }

        ExaAiSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->update([
                'status' => 'stopped',
                'stopped_at' => now(),
            ]);

        $durationLabel = (string) ($payload['duration'] ?? 'manual');
        $endsAt = $this->resolveDurationEnd($durationLabel);

        $session = ExaAiSession::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'allocation_id' => $allocation->id,
            'strategy_definition_id' => $strategy->id,
            'strategy_version_id' => $currentVersion->id,
            'mode' => $mode,
            'status' => 'active',
            'risk_level' => $strategy->risk_level,
            'duration_label' => $durationLabel,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'max_daily_loss' => $this->fmt((string) ($payload['max_daily_loss'] ?? '0')),
            'max_drawdown_percent' => $payload['max_drawdown_percent'] ?? null,
            'max_open_positions' => min((int) ($payload['max_open_positions'] ?? $effective['entitlements']['maximum_positions']), (int) $effective['entitlements']['maximum_positions']),
            'eligible_markets' => $payload['eligible_markets'] ?? ['BTC/USDT', 'ETH/USDT', 'SOL/USDT'],
            'constraints' => array_merge($strategy->default_constraints ?? [], $payload['constraints'] ?? []),
            'stop_conditions' => [
                'daily_loss' => (string) ($payload['max_daily_loss'] ?? '0'),
                'drawdown_percent' => (string) ($payload['max_drawdown_percent'] ?? '0'),
            ],
            'metadata' => [
                'activated_by' => 'user',
                'plan_code' => $subscription->plan->code,
                'strategy_version' => $currentVersion->version,
                'live_authorized' => $mode === 'live',
                'effective_entitlement_mode' => $effective['mode'],
            ],
        ]);

        $this->notifications->create(
            $user,
            'exaai_status',
            'ExaAI activated',
            sprintf('%s strategy is now active.', $strategy->name),
            channels: ['in_app'],
            data: ['page' => 'exaai']
        );

        $this->audit($user->id, $session->id, 'session.started', 'ExaAI session activated.', [
            'strategy' => $strategy->code,
            'allocation_id' => $allocation->id,
            'mode' => $session->mode,
        ]);

        return $session->load(['subscription.plan', 'allocation', 'strategy', 'strategyVersion']);
    }

    public function getCurrentSession(User $user): ?ExaAiSession
    {
        return ExaAiSession::query()
            ->with(['subscription.plan', 'allocation', 'strategy', 'strategyVersion'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('id')
            ->first();
    }

    public function pauseSession(User $user, int $sessionId): ExaAiSession
    {
        $session = $this->sessionForUser($user, $sessionId);
        $session->update(['status' => 'paused', 'paused_at' => now()]);
        $this->audit($user->id, $session->id, 'session.paused', 'ExaAI session paused.', []);
        return $session->fresh(['subscription.plan', 'allocation', 'strategy', 'strategyVersion']);
    }

    public function resumeSession(User $user, int $sessionId): ExaAiSession
    {
        $session = $this->sessionForUser($user, $sessionId);
        $session->update(['status' => 'active', 'paused_at' => null]);
        $this->audit($user->id, $session->id, 'session.resumed', 'ExaAI session resumed.', []);
        return $session->fresh(['subscription.plan', 'allocation', 'strategy', 'strategyVersion']);
    }

    public function stopSession(User $user, int $sessionId): ExaAiSession
    {
        $session = $this->sessionForUser($user, $sessionId);
        $session->update(['status' => 'stopped', 'stopped_at' => now()]);
        $this->audit($user->id, $session->id, 'session.stopped', 'ExaAI session stopped.', []);
        return $session->fresh(['subscription.plan', 'allocation', 'strategy', 'strategyVersion']);
    }

    public function positions(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return ExaAiOrder::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'open'])
            ->orderByDesc('opened_at')
            ->paginate($perPage);
    }

    public function trades(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return ExaAiOrder::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function performance(User $user, string $period = '30d'): array
    {
        $from = match ($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            'all' => null,
            default => now()->subDays(30),
        };

        $query = ExaAiOrder::query()->where('user_id', $user->id);
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        $orders = $query->get();
        $completed = $orders->where('status', 'closed')->values();
        $open = $orders->whereIn('status', ['pending', 'open'])->values();

        $realized = $this->sumCollection($completed, 'realized_pnl');
        $unrealized = $this->sumCollection($open, 'unrealized_pnl');
        $fees = $this->sumCollection($orders, 'fees');
        $wins = $completed->filter(fn (ExaAiOrder $order): bool => $this->compare((string) $order->realized_pnl, '0') > 0)->count();
        $losses = $completed->filter(fn (ExaAiOrder $order): bool => $this->compare((string) $order->realized_pnl, '0') < 0)->count();

        return [
            'period' => $period,
            'net_pnl' => $this->add($realized, $unrealized),
            'realized_pnl' => $realized,
            'unrealized_pnl' => $unrealized,
            'trading_fees' => $fees,
            'total_trades' => $completed->count(),
            'winning_trades' => $wins,
            'losing_trades' => $losses,
            'win_rate' => $completed->count() > 0 ? round(($wins / $completed->count()) * 100, 2) : null,
            'profit_factor' => $this->profitFactor($completed),
            'max_drawdown_percent' => $this->calculateMaxDrawdown($completed),
            'equity_curve' => $this->equityCurve($completed),
        ];
    }

    public function adminOverview(): array
    {
        return [
            'total_subscribers' => ExaAiSubscription::query()->where('status', 'active')->count(),
            'active_sessions' => ExaAiSession::query()->where('status', 'active')->count(),
            'paused_sessions' => ExaAiSession::query()->where('status', 'paused')->count(),
            'allocated_capital' => (string) ExaAiCapitalAllocation::query()->where('status', 'active')->sum('amount'),
            'reserved_capital' => (string) ExaAiCapitalAllocation::query()->where('status', 'active')->sum('reserved_amount'),
            'open_orders' => ExaAiOrder::query()->whereIn('status', ['pending', 'open'])->count(),
            'completed_orders' => ExaAiOrder::query()->where('status', 'closed')->count(),
        ];
    }

    public function adminPlans(): Collection
    {
        return ExaAiPlan::query()->orderBy('id')->get();
    }

    public function adminStrategies(): Collection
    {
        return ExaAiStrategyDefinition::query()->with('versions')->orderBy('id')->get();
    }

    public function adminSessions(int $perPage = 25): LengthAwarePaginator
    {
        return ExaAiSession::query()
            ->with(['user', 'subscription.plan', 'allocation', 'strategy', 'strategyVersion'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function adminSubscriptions(int $perPage = 25): LengthAwarePaginator
    {
        return ExaAiSubscription::query()
            ->with(['user', 'plan'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function adminTrades(int $perPage = 25): LengthAwarePaginator
    {
        return ExaAiOrder::query()
            ->with(['user', 'session'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function adminAuditLogs(int $perPage = 25): LengthAwarePaginator
    {
        return ExaAiAuditLog::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function sessionForUser(User $user, int $sessionId): ExaAiSession
    {
        $session = ExaAiSession::query()->where('user_id', $user->id)->find($sessionId);
        if (! $session) {
            throw new RuntimeException('ExaAI session not found.');
        }

        return $session;
    }

    private function resolveDurationEnd(string $durationLabel): ?CarbonImmutable
    {
        return match (strtolower($durationLabel)) {
            '24h' => CarbonImmutable::now()->addDay(),
            '7d' => CarbonImmutable::now()->addDays(7),
            '30d' => CarbonImmutable::now()->addDays(30),
            '90d' => CarbonImmutable::now()->addDays(90),
            default => null,
        };
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower($mode);
        return match ($mode) {
            'demo', 'paper' => 'paper',
            'shadow' => 'shadow',
            'live' => 'live',
            default => throw new RuntimeException('Unsupported ExaAI mode.'),
        };
    }

    private function safeEntitlements(User $user): array
    {
        try {
            $effective = $this->entitlements->effectiveFor($user, ['log_compliance' => false]);
            unset($effective['subscription'], $effective['plan'], $effective['market']);
            return $effective;
        } catch (\Throwable $exception) {
            return [
                'allowed' => false,
                'mode' => 'NO_NEW_RISK',
                'reasons' => ['ENTITLEMENT_EVALUATION_FAILED'],
            ];
        }
    }

    private function sumCollection(iterable $rows, string $field): string
    {
        $sum = '0';
        foreach ($rows as $row) {
            $sum = $this->add($sum, $this->fmt((string) data_get($row, $field, '0')));
        }
        return $sum;
    }

    private function profitFactor(Collection $completed): ?float
    {
        $wins = '0';
        $losses = '0';
        foreach ($completed as $order) {
            $pnl = $this->fmt((string) $order->realized_pnl);
            if ($this->compare($pnl, '0') > 0) {
                $wins = $this->add($wins, $pnl);
            } elseif ($this->compare($pnl, '0') < 0) {
                $losses = $this->add($losses, $this->mul($pnl, '-1'));
            }
        }

        if ($this->compare($losses, '0') === 0) {
            return null;
        }

        return round((float) bcdiv($wins, $losses, 8), 4);
    }

    private function calculateMaxDrawdown(Collection $completed): ?float
    {
        if ($completed->isEmpty()) {
            return null;
        }

        $equity = 0.0;
        $peak = 0.0;
        $drawdown = 0.0;
        foreach ($completed as $order) {
            $equity += (float) $order->realized_pnl;
            $peak = max($peak, $equity);
            if ($peak > 0) {
                $drawdown = max($drawdown, (($peak - $equity) / $peak) * 100);
            }
        }

        return round($drawdown, 2);
    }

    private function equityCurve(Collection $completed): array
    {
        $equity = '0';
        return $completed
            ->sortBy('closed_at')
            ->map(function (ExaAiOrder $order) use (&$equity): array {
                $equity = $this->add($equity, $this->fmt((string) $order->realized_pnl));
                return [
                    'time' => optional($order->closed_at)->toISOString(),
                    'equity' => $equity,
                ];
            })
            ->values()
            ->all();
    }

    private function audit(?int $userId, ?int $sessionId, string $eventType, string $message, array $context): void
    {
        ExaAiAuditLog::query()->create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'severity' => 'info',
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    private function fmt(string $value): string
    {
        $this->assertDecimalSupport();

        return bcadd($value, '0', self::SCALE);
    }

    private function add(string $left, string $right): string
    {
        $this->assertDecimalSupport();

        return bcadd($left, $right, self::SCALE);
    }

    private function sub(string $left, string $right): string
    {
        $this->assertDecimalSupport();

        return bcsub($left, $right, self::SCALE);
    }

    private function mul(string $left, string $right): string
    {
        $this->assertDecimalSupport();

        return bcmul($left, $right, self::SCALE);
    }

    private function compare(string $left, string $right): int
    {
        $this->assertDecimalSupport();

        return bccomp($left, $right, self::SCALE);
    }

    private function assertDecimalSupport(): void
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('BCMath is required for ExaAI financial calculations.');
        }
    }
}
