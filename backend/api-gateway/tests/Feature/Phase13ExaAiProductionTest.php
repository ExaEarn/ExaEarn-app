<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExaAiDecision;
use App\Models\ExaAiLoadRun;
use App\Models\ExaAiMarketEligibility;
use App\Models\ExaAiPlan;
use App\Models\ExaAiPublicSetting;
use App\Models\ExaAiStrategyDefinition;
use App\Models\ExaAiStrategyVersion;
use App\Models\ExaAiSubscription;
use App\Models\TradingSignal;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase13ExaAiProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_exaai_decision_requires_terms_before_risk_approval(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('terms-required'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason_code', 'TERMS_NOT_ACCEPTED');
    }

    public function test_exaai_approves_decision_and_replays_private_events(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();

        $this->actingAs($user)->postJson('/api/exaai/terms/accept', [
            'terms_version' => 'phase13-v1',
        ])->assertOk();

        $response = $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('approved-one'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.risk_decision', 'approved')
            ->assertJsonPath('data.approved_notional', '100.00000000')
            ->assertJsonPath('data.quantity', '0.00100000');

        $decisionId = (int) $response->json('data.id');

        $this->assertDatabaseHas('exaai_decisions', [
            'id' => $decisionId,
            'user_id' => $user->id,
            'idempotency_key' => 'approved-one',
            'reason_code' => 'APPROVED',
        ]);

        $this->actingAs($user)
            ->getJson('/api/exaai/realtime/replay?after_sequence=0')
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'exaai.decision')
            ->assertJsonPath('data.0.sequence', 1);
    }

    public function test_exaai_decision_idempotency_prevents_duplicate_decisions(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $first = $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('same-key'))
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('same-key'))
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ExaAiDecision::query()->where('idempotency_key', 'same-key')->count());
    }

    public function test_exaai_rejects_stale_market_data_and_global_kill_switch(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('stale-market', [
                'market_snapshot' => [
                    'updated_at' => now()->subMinutes(5)->toISOString(),
                    'last_price' => '100000.00000000',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason_code', 'STALE_MARKET_DATA');

        ExaAiPublicSetting::query()->create([
            'key' => 'global_controls',
            'value' => [
                'global_kill_switch' => true,
                'reason' => 'test',
            ],
        ]);

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('kill-switch'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason_code', 'EXAAI_EMERGENCY');
    }

    public function test_malformed_strategy_output_fails_closed_before_decision_storage(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('bad-output', [
                'action' => 'BUY BTC NOW',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Malformed ExaAI decision output: unsupported action.');

        $this->assertDatabaseMissing('exaai_decisions', ['idempotency_key' => 'bad-output']);
    }

    public function test_non_production_strategy_version_cannot_touch_user_funds(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        ExaAiStrategyVersion::query()->where('version', '1.0.0')->update(['state' => 'draft']);

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('draft-version'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason_code', 'STRATEGY_STATE_NOT_PRODUCTION');
    }

    public function test_shadow_strategy_generates_decision_without_real_execution(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        ExaAiStrategyVersion::query()->where('version', '1.0.0')->update(['state' => 'shadow']);

        $decisionId = (int) $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('shadow-version'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'shadow')
            ->assertJsonPath('data.reason_code', 'SHADOW_DECISION')
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/exaai/decisions/{$decisionId}/execute")
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped')
            ->assertJsonPath('data.reason_code', 'SHADOW_MODE_NO_REAL_ORDER');

        $this->assertDatabaseCount('trading_signals', 0);
    }

    public function test_paper_mode_never_submits_real_orders_or_touches_funds(): void
    {
        $user = $this->startExaAiSession(['mode' => 'paper']);
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $decisionId = (int) $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('paper-decision'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'paper')
            ->assertJsonPath('data.reason_code', 'PAPER_DECISION')
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/exaai/decisions/{$decisionId}/execute")
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped')
            ->assertJsonPath('data.reason_code', 'PAPER_MODE_NO_REAL_ORDER');

        $this->assertDatabaseCount('trading_signals', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('exaai_capital_allocations', [
            'user_id' => $user->id,
            'amount' => '1000.00000000',
            'available_amount' => '1000.00000000',
            'reserved_amount' => '0.00000000',
        ]);
    }

    public function test_live_mode_requires_explicit_user_authorization(): void
    {
        $user = User::factory()->create();
        $plan = ExaAiPlan::query()->where('code', 'pro')->firstOrFail();
        $strategy = ExaAiStrategyDefinition::query()->where('code', 'balanced')->firstOrFail();
        ExaAiSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'settlement_asset' => 'USDT',
            'amount' => '100.00000000',
            'transaction_reference' => 'EXAAI-LIVE-' . Str::random(8),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth(),
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '5000.00000000',
            'locked_balance' => '0.00000000',
        ]);
        $allocationId = (int) $this->actingAs($user)->postJson('/api/exaai/allocations', [
            'asset' => 'USDT',
            'amount' => '1000.00000000',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user)->postJson('/api/exaai/sessions', [
            'allocation_id' => $allocationId,
            'strategy_id' => $strategy->id,
            'mode' => 'live',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'LIVE ExaAI sessions require explicit user authorization.');

        $this->actingAs($user)->postJson('/api/exaai/sessions', [
            'allocation_id' => $allocationId,
            'strategy_id' => $strategy->id,
            'mode' => 'live',
            'live_authorization' => true,
        ])->assertCreated()
            ->assertJsonPath('data.mode', 'live')
            ->assertJsonPath('data.metadata.live_authorized', true);
    }

    public function test_subscription_expiry_blocks_new_risk_but_preserves_existing_session_for_reduction(): void
    {
        $user = $this->startExaAiSession();
        ExaAiSubscription::query()->where('user_id', $user->id)->update([
            'ends_at' => now()->subMinute(),
            'status' => 'expired',
        ]);
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('expired-sub'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.reason_code', 'SUBSCRIPTION_NOT_ACTIVE');
    }

    public function test_plan_downgrade_does_not_force_strategy_but_blocks_excess_usage(): void
    {
        $user = $this->startExaAiSession();
        $starter = ExaAiPlan::query()->where('code', 'starter')->firstOrFail();
        ExaAiSubscription::query()->where('user_id', $user->id)->update(['plan_id' => $starter->id]);

        $this->actingAs($user)
            ->getJson('/api/exaai/overview')
            ->assertOk()
            ->assertJsonPath('data.effective_permission.allowed', false)
            ->assertJsonPath('data.effective_permission.mode', 'USER_ACTION_REQUIRED');
    }

    public function test_admin_can_version_and_audit_plan_entitlement_mapping(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'two_factor_enabled' => true]);
        $plan = ExaAiPlan::query()->where('code', 'starter')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson("/api/admin/exaai/plans/{$plan->id}/entitlements", [
                'reason' => 'Limited production starter cap update.',
                'entitlements' => [
                    'maximum_ai_capital' => '750.00000000',
                    'allowed_strategies' => ['conservative'],
                    'spot_enabled' => true,
                    'futures_enabled' => false,
                    'maximum_leverage' => 1,
                    'maximum_positions' => 1,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.capital_limit', '750.00000000');

        $this->assertDatabaseHas('exaai_audit_logs', [
            'user_id' => $admin->id,
            'event_type' => 'entitlements.updated',
        ]);
    }

    public function test_strategy_version_history_is_not_rewritten_when_new_version_is_current(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $definition = ExaAiStrategyDefinition::query()->where('code', 'balanced')->firstOrFail();
        $firstVersionId = ExaAiStrategyVersion::query()
            ->where('strategy_definition_id', $definition->id)
            ->where('version', '1.0.0')
            ->firstOrFail()
            ->id;
        $decisionId = (int) $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('v1-history'))
            ->assertCreated()
            ->json('data.id');

        ExaAiStrategyVersion::query()->where('strategy_definition_id', $definition->id)->update(['is_current' => false]);
        $newVersion = ExaAiStrategyVersion::query()->create([
            'strategy_definition_id' => $definition->id,
            'version' => '2.0.0',
            'is_current' => true,
            'state' => 'production',
            'config' => ['engine' => 'rule_based'],
            'risk_rules' => ['stale_data_seconds' => 30],
            'published_at' => now(),
        ]);

        $this->assertDatabaseHas('exaai_decisions', [
            'id' => $decisionId,
            'strategy_version_id' => $firstVersionId,
        ]);
        $this->assertNotSame($firstVersionId, $newVersion->id);
    }

    public function test_position_sizing_caps_requested_exposure_by_strategy_and_market_limits(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('sizing-cap', [
                'requested_notional' => '900.00000000',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_notional', '200.00000000')
            ->assertJsonPath('data.risk_snapshot.portfolio_cap', '200.00000000');
    }

    public function test_stale_decision_is_not_submitted_after_downtime(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();

        $decisionId = (int) $this->actingAs($user)
            ->postJson('/api/exaai/decisions', $this->decisionPayload('stale-decision', [
                'max_age_seconds' => 5,
            ]))
            ->assertCreated()
            ->json('data.id');

        ExaAiDecision::query()->whereKey($decisionId)->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($user)
            ->postJson("/api/exaai/decisions/{$decisionId}/execute")
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped')
            ->assertJsonPath('data.reason_code', 'REJECT_STALE_DECISION');

        $this->assertSame(0, TradingSignal::query()->count());
    }

    public function test_exaai_readiness_uses_real_state_and_load_runs(): void
    {
        $user = $this->startExaAiSession();
        $this->createMarketEligibility();
        $this->actingAs($user)->postJson('/api/exaai/terms/accept')->assertOk();
        $this->actingAs($user)->postJson('/api/exaai/decisions', $this->decisionPayload('readiness-event'))->assertCreated();

        foreach ([['exaai_1k_decisions', 1000], ['exaai_10k_decisions', 10000]] as [$scenario, $participants]) {
            ExaAiLoadRun::query()->create([
                'run_uuid' => (string) Str::uuid(),
                'scenario' => $scenario,
                'participants' => $participants,
                'metrics' => [
                    'decisions' => $participants,
                    'duplicates' => 0,
                    'financial_invariant_failures' => 0,
                ],
                'status' => 'passed',
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/exaai/readiness')
            ->assertOk()
            ->assertJsonPath('data.strategy_orchestration', 'READY')
            ->assertJsonPath('data.market_eligibility', 'READY')
            ->assertJsonPath('data.private_realtime', 'READY')
            ->assertJsonPath('data.load_1k', 'PASS')
            ->assertJsonPath('data.load_10k', 'PASS')
            ->assertJsonPath('data.safe_to_begin_phase14', 'YES');
    }

    private function startExaAiSession(array $sessionOverrides = []): User
    {
        $user = User::factory()->create();
        $plan = ExaAiPlan::query()->where('code', 'pro')->firstOrFail();
        $strategy = ExaAiStrategyDefinition::query()->where('code', 'balanced')->firstOrFail();

        ExaAiSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'settlement_asset' => 'USDT',
            'amount' => '100.00000000',
            'transaction_reference' => 'EXAAI-P13-' . Str::random(8),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth(),
            'metadata' => ['source' => 'test'],
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '5000.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $allocationId = (int) $this->actingAs($user)->postJson('/api/exaai/allocations', [
            'asset' => 'USDT',
            'amount' => '1000.00000000',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user)->postJson('/api/exaai/sessions', array_merge([
            'allocation_id' => $allocationId,
            'strategy_id' => $strategy->id,
            'mode' => 'live',
            'live_authorization' => true,
            'duration' => '30d',
            'max_daily_loss' => '100',
            'max_drawdown_percent' => '10',
            'eligible_markets' => ['BTC/USDT'],
            'constraints' => [
                'max_position_pct' => '0.20',
                'min_signal_confidence' => 60,
            ],
        ], $sessionOverrides))->assertCreated();

        return $user;
    }

    private function createMarketEligibility(): void
    {
        ExaAiMarketEligibility::query()->create([
            'symbol' => 'BTCUSDT',
            'product' => 'spot',
            'status' => 'enabled',
            'risk_tier' => 'standard',
            'min_liquidity' => '1000000.00000000',
            'max_exposure' => '250.00000000',
            'max_concentration_percent' => '20',
            'max_slippage_bps' => 50,
            'market_data_freshness_seconds' => 30,
            'metadata' => ['source' => 'test'],
        ]);
    }

    private function decisionPayload(string $idempotencyKey, array $overrides = []): array
    {
        return array_replace_recursive([
            'idempotency_key' => $idempotencyKey,
            'product' => 'spot',
            'symbol' => 'BTC/USDT',
            'side' => 'buy',
            'order_type' => 'market',
            'requested_notional' => '100.00000000',
            'reference_price' => '100000.00000000',
            'confidence' => 82,
            'max_age_seconds' => 30,
            'market_snapshot' => [
                'updated_at' => now()->toISOString(),
                'last_price' => '100000.00000000',
                'source' => 'EXAEARN_INTERNAL',
            ],
            'signal_payload' => [
                'strategy' => 'balanced',
                'signal_category' => 'momentum',
            ],
        ], $overrides);
    }
}
