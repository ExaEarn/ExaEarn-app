<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiDecision;
use App\Models\ExaAiPortfolio;
use App\Models\ExaAiTermAcceptance;
use App\Models\TradingSignal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ExaAiProductionService
{
    private const SCALE = 8;

    public function __construct(
        private readonly ExaAiService $exaAi,
        private readonly ExaAiStrategyEngineService $strategyEngine,
        private readonly ExaAiProductionRiskService $risk,
        private readonly ExaAiExecutionService $execution,
        private readonly ExaAiRealtimeService $realtime,
    ) {
    }

    public function acceptTerms(User $user, string $version = 'phase13-v1'): ExaAiTermAcceptance
    {
        return ExaAiTermAcceptance::query()->firstOrCreate([
            'user_id' => $user->id,
            'terms_version' => $version,
            'acceptance_scope' => 'exaai_automated_trading',
        ], [
            'accepted_at' => now(),
            'metadata' => ['source' => 'api'],
        ]);
    }

    public function currentPortfolio(User $user): ?ExaAiPortfolio
    {
        return ExaAiPortfolio::query()
            ->with(['session.subscription', 'allocation', 'strategy', 'strategyVersion'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('id')
            ->first();
    }

    public function ensurePortfolio(User $user): ExaAiPortfolio
    {
        $session = $this->exaAi->getCurrentSession($user);
        if (! $session) {
            throw new RuntimeException('An active ExaAI session is required.');
        }

        return DB::transaction(function () use ($session, $user): ExaAiPortfolio {
            $portfolio = ExaAiPortfolio::query()
                ->where('user_id', $user->id)
                ->where('session_id', $session->id)
                ->lockForUpdate()
                ->first();

            if ($portfolio) {
                return $portfolio->fresh(['session.subscription', 'allocation']);
            }

            $allocation = $session->allocation()->lockForUpdate()->firstOrFail();

            return ExaAiPortfolio::query()->create([
                'user_id' => $user->id,
                'session_id' => $session->id,
                'allocation_id' => $allocation->id,
                'strategy_definition_id' => $session->strategy_definition_id,
                'strategy_version_id' => $session->strategy_version_id,
                'asset' => (string) $allocation->asset,
                'mode' => (string) $session->mode,
                'status' => 'active',
                'allocated_amount' => (string) $allocation->amount,
                'available_amount' => (string) $allocation->available_amount,
                'reserved_amount' => (string) $allocation->reserved_amount,
                'deployed_amount' => '0',
                'equity_amount' => (string) $allocation->amount,
                'high_water_mark' => (string) $allocation->amount,
                'risk_profile' => (string) $session->risk_level,
                'limits' => $session->constraints ?? [],
                'metadata' => ['created_from_session' => $session->id],
            ])->fresh(['session.subscription', 'allocation']);
        });
    }

    public function createDecision(User $user, array $payload): ExaAiDecision
    {
        $portfolio = $this->ensurePortfolio($user);
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            throw new RuntimeException('ExaAI decision idempotency key is required.');
        }

        return DB::transaction(function () use ($idempotencyKey, $payload, $portfolio, $user): ExaAiDecision {
            $existing = ExaAiDecision::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $portfolio = ExaAiPortfolio::query()
                ->with(['user', 'session.subscription.plan', 'session.strategyVersion', 'strategy', 'strategyVersion'])
                ->whereKey($portfolio->id)
                ->lockForUpdate()
                ->firstOrFail();
            $version = $portfolio->session?->strategyVersion;
            if (! $version) {
                throw new RuntimeException('ExaAI strategy version is unavailable.');
            }

            $normalized = $this->strategyEngine->normalizeDecisionOutput($version, $payload);
            $state = strtolower((string) ($version->state ?? 'active'));
            $isPaper = $portfolio->mode === 'paper';
            $isShadow = $portfolio->mode === 'shadow' || $this->strategyEngine->shadowState($state);
            $stateAllowsOrders = $this->strategyEngine->productionStateAllowsRealOrders($state);
            $risk = ($isPaper || $isShadow)
                ? [
                    'approved' => true,
                    'reason_code' => $isPaper ? 'PAPER_DECISION' : 'SHADOW_DECISION',
                    'approved_notional' => '0.00000000',
                    'quantity' => '0.00000000',
                    'risk_snapshot' => ['paper_mode' => $isPaper, 'shadow_mode' => $isShadow, 'strategy_state' => $state],
                ]
                : ($stateAllowsOrders ? $this->risk->evaluate($portfolio, $normalized) : [
                    'approved' => false,
                    'reason_code' => 'STRATEGY_STATE_NOT_PRODUCTION',
                    'approved_notional' => '0.00000000',
                    'quantity' => '0.00000000',
                    'risk_snapshot' => ['strategy_state' => $state],
                ]);
            $sequence = ((int) ExaAiDecision::query()->where('portfolio_id', $portfolio->id)->max('sequence')) + 1;
            $referencePrice = (string) ($normalized['reference_price'] ?? data_get($normalized, 'market_snapshot.last_price', '0'));

            $decision = ExaAiDecision::query()->create([
                'decision_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'session_id' => $portfolio->session_id,
                'portfolio_id' => $portfolio->id,
                'strategy_definition_id' => $portfolio->strategy_definition_id,
                'strategy_version_id' => $portfolio->strategy_version_id,
                'idempotency_key' => $idempotencyKey,
                'product' => $normalized['product'],
                'symbol' => $normalized['symbol'],
                'side' => $normalized['side'],
                'order_type' => strtolower((string) ($normalized['order_type'] ?? 'market')),
                'requested_notional' => $this->fmt((string) ($normalized['requested_notional'] ?? $normalized['target_exposure'] ?? '0')),
                'approved_notional' => $risk['approved_notional'],
                'reference_price' => $this->fmt($referencePrice),
                'quantity' => $risk['quantity'],
                'confidence' => (int) ($normalized['confidence'] ?? 0),
                'risk_decision' => $risk['approved'] ? 'approved' : 'rejected',
                'status' => $isPaper ? 'paper' : ($isShadow ? 'shadow' : ($risk['approved'] ? 'approved' : 'rejected')),
                'reason_code' => $risk['reason_code'],
                'signal_payload' => array_merge($normalized['signal_payload'] ?? [], [
                    'action' => $normalized['action'],
                    'rationale_code' => $normalized['rationale_code'],
                    'stop_conditions' => $normalized['stop_conditions'],
                ]),
                'market_snapshot' => $normalized['market_snapshot'] ?? [],
                'risk_snapshot' => $risk['risk_snapshot'],
                'execution_plan' => [
                    'route' => 'existing_' . strtolower((string) ($payload['product'] ?? 'spot')) . '_oms',
                    'source' => 'EXAAI',
                    'fail_closed' => true,
                ],
                'sequence' => $sequence,
                'decided_at' => now(),
                'expires_at' => now()->addSeconds((int) data_get($normalized, 'max_age_seconds', 30)),
            ]);

            $this->realtime->publish($user->id, 'exaai.private', 'exaai.decision', [
                'decision_uuid' => $decision->decision_uuid,
                'status' => $decision->status,
                'reason_code' => $decision->reason_code,
                'sequence' => $decision->sequence,
            ]);

            return $decision;
        });
    }

    public function executeDecision(User $user, int $decisionId): ExaAiDecision
    {
        return DB::transaction(function () use ($decisionId, $user): ExaAiDecision {
            $decision = ExaAiDecision::query()
                ->where('user_id', $user->id)
                ->whereKey($decisionId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($decision->status, ['submitted', 'filled', 'skipped', 'failed'], true)) {
                return $decision;
            }

            if (in_array($decision->status, ['paper', 'shadow'], true)) {
                $mode = $decision->status;
                $decision->update([
                    'status' => 'skipped',
                    'reason_code' => $mode === 'paper' ? 'PAPER_MODE_NO_REAL_ORDER' : 'SHADOW_MODE_NO_REAL_ORDER',
                    'execution_result' => [
                        'paper_mode' => $mode === 'paper',
                        'shadow_mode' => $mode === 'shadow',
                        'real_order_submitted' => false,
                    ],
                ]);

                return $decision->fresh();
            }

            if ($decision->status !== 'approved') {
                throw new RuntimeException('Only approved ExaAI decisions can execute.');
            }

            if ($decision->expires_at && $decision->expires_at->isPast()) {
                $decision->update(['status' => 'skipped', 'reason_code' => 'REJECT_STALE_DECISION']);
                return $decision->fresh();
            }

            $session = $decision->session()->with(['subscription', 'allocation', 'strategy', 'strategyVersion'])->firstOrFail();
            $signal = TradingSignal::query()->create([
                'user_id' => $user->id,
                'symbol' => $decision->symbol,
                'signal_type' => strtoupper($decision->side) === 'SELL' ? 'SELL' : 'BUY',
                'confidence' => $decision->confidence,
                'reason' => 'ExaAI production decision ' . $decision->decision_uuid,
                'technical_indicators' => $decision->signal_payload ?? [],
                'suggested_entry' => (string) $decision->reference_price,
                'market_condition' => 'production_automation',
                'volatility_level' => data_get($decision->market_snapshot, 'volatility', 'normal'),
                'trend_strength' => data_get($decision->signal_payload, 'trend_strength', 'unknown'),
                'risk_reward_ratio' => '1.00',
                'ai_reasoning' => 'Deterministic ExaAI strategy decision. AI signals are gated by server risk controls.',
                'is_active' => true,
                'expires_at' => $decision->expires_at,
            ]);

            $order = $this->execution->executeSignal($session, $signal);
            $decision->update([
                'status' => in_array($order->status, ['closed', 'filled'], true) ? 'filled' : 'submitted',
                'executed_at' => now(),
                'execution_result' => [
                    'exaai_order_id' => $order->id,
                    'market_type' => $order->market_type,
                    'source_order_uuid' => $order->source_order_uuid,
                    'source_futures_order_uuid' => $order->source_futures_order_uuid,
                ],
            ]);

            $this->realtime->publish($user->id, 'exaai.private', 'exaai.execution', [
                'decision_uuid' => $decision->decision_uuid,
                'status' => $decision->status,
                'order_id' => $order->id,
            ]);

            return $decision->fresh();
        });
    }

    public function replay(User $user, int $afterSequence = 0): array
    {
        return $this->realtime->replay($user->id, 'exaai.private', $afterSequence)->all();
    }

    private function fmt(string $value): string
    {
        if (! function_exists('bcadd')) {
            throw new RuntimeException('BCMath is required for ExaAI financial calculations.');
        }

        return bcadd(trim($value), '0', self::SCALE);
    }
}
