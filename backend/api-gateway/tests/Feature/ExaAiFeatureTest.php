<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExaAiPlan;
use App\Models\ExaAiStrategyDefinition;
use App\Models\ExaAiSubscription;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaAiFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_exaai_plans_and_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exaai/plans')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $this->actingAs($user)
            ->getJson('/api/exaai/overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.subscription_status', 'inactive')
            ->assertJsonPath('data.status.session_status', 'stopped');
    }

    public function test_user_can_allocate_capital_and_start_exaai_session(): void
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
            'transaction_reference' => 'EXAAI-SUB-TEST-001',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth(),
            'metadata' => ['source' => 'test'],
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '1500.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $allocationResponse = $this->actingAs($user)->postJson('/api/exaai/allocations', [
            'asset' => 'USDT',
            'amount' => '500.00000000',
        ]);

        $allocationResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset', 'USDT')
            ->assertJsonPath('data.amount', '500.00000000');

        $allocationId = (int) $allocationResponse->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/exaai/allocations/active')
            ->assertOk()
            ->assertJsonPath('data.id', $allocationId)
            ->assertJsonPath('data.asset', 'USDT');

        $sessionResponse = $this->actingAs($user)->postJson('/api/exaai/sessions', [
            'allocation_id' => $allocationId,
            'strategy_id' => $strategy->id,
            'duration' => '30d',
            'max_daily_loss' => '50',
            'max_drawdown_percent' => '8',
            'eligible_markets' => ['BTC/USDT', 'ETH/USDT'],
        ]);

        $sessionResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.risk_level', 'balanced');

        $this->assertDatabaseHas('exaai_capital_allocations', [
            'user_id' => $user->id,
            'asset' => 'USDT',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('exaai_sessions', [
            'user_id' => $user->id,
            'allocation_id' => $allocationId,
            'strategy_definition_id' => $strategy->id,
            'status' => 'active',
            'mode' => 'live',
        ]);
    }

    public function test_allocation_rejects_amount_above_transferable_balance(): void
    {
        $user = User::factory()->create();
        $plan = ExaAiPlan::query()->where('code', 'starter')->firstOrFail();

        ExaAiSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'settlement_asset' => 'USDT',
            'amount' => '20.00000000',
            'transaction_reference' => 'EXAAI-SUB-TEST-002',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth(),
            'metadata' => ['source' => 'test'],
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '100.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $this->actingAs($user)
            ->postJson('/api/exaai/allocations', [
                'asset' => 'USDT',
                'amount' => '500.00000000',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Allocation exceeds available Unified Trading capital.');
    }

    public function test_session_rejects_strategy_not_permitted_by_current_plan(): void
    {
        $user = User::factory()->create();
        $plan = ExaAiPlan::query()->where('code', 'starter')->firstOrFail();
        $strategy = ExaAiStrategyDefinition::query()->where('code', 'balanced')->firstOrFail();

        ExaAiSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'settlement_asset' => 'USDT',
            'amount' => '20.00000000',
            'transaction_reference' => 'EXAAI-SUB-TEST-003',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renewal_at' => now()->addMonth(),
            'metadata' => ['source' => 'test'],
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'available_balance' => '800.00000000',
            'locked_balance' => '0.00000000',
        ]);

        $allocationId = (int) $this->actingAs($user)->postJson('/api/exaai/allocations', [
            'asset' => 'USDT',
            'amount' => '100.00000000',
        ])->json('data.id');

        $this->actingAs($user)
            ->postJson('/api/exaai/sessions', [
                'allocation_id' => $allocationId,
                'strategy_id' => $strategy->id,
                'duration' => '30d',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Current plan does not permit the selected strategy.');
    }
}