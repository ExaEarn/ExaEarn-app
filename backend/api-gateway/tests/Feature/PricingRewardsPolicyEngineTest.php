<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\P2P\Services\P2PFeeService;
use App\Models\Admin;
use App\Models\PricingDecision;
use App\Models\PricingRule;
use App\Models\PricingRuleChange;
use App\Models\Permission;
use App\Models\RewardPolicyDecision;
use App\Models\RewardPolicyRule;
use App\Models\Role;
use App\Models\User;
use App\Services\FeeCalculator;
use App\Services\PermissionService;
use App\Services\PricingPolicyEngine;
use App\Services\PricingProductMigrationService;
use App\Services\RewardPolicyEngine;
use App\Services\Custody\WithdrawalFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PricingRewardsPolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
        config(['pricing.shadow_mode' => false]);
    }

    public function test_pricing_engine_applies_precedence_and_snapshots_quote(): void
    {
        $user = User::factory()->create();
        $this->pricingRule(['percentage_bps' => '20', 'precedence_scope' => 'PRODUCT_DEFAULT']);
        $this->pricingRule(['percentage_bps' => '5', 'precedence_scope' => 'VIP', 'vip_tier' => 'VIP_3', 'priority' => 10]);

        $decision = app(PricingPolicyEngine::class)->quote($user, [
            'product' => 'spot',
            'operation' => 'taker_fee',
            'amount' => '1000',
            'asset' => 'USDT',
            'vip_tier' => 'VIP_3',
        ]);

        $this->assertSame('0.500000000000000000', (string) $decision->fee_amount);
        $this->assertSame('999.500000000000000000', (string) $decision->net_amount);
        $this->assertSame('VIP', $decision->rule_snapshot['precedence_scope']);
        $this->assertTrue($decision->expires_at->greaterThan(now()));
    }

    public function test_negative_fee_requires_explicit_rebate_rule(): void
    {
        $this->pricingRule(['fee_type' => 'CUSTOM_CONTRACT', 'fixed_value' => '-1', 'allow_negative' => false]);

        $this->expectException(RuntimeException::class);
        app(PricingPolicyEngine::class)->preview([
            'product' => 'SPOT',
            'operation' => 'TAKER_FEE',
            'amount' => '100',
            'asset' => 'USDT',
        ]);
    }

    public function test_maker_checker_rule_approval_and_admin_routes(): void
    {
        $maker = $this->admin('maker@example.com');
        $checker = $this->admin('checker@example.com');

        $change = $this->actingAs($maker)->postJson('/api/admin/v1/pricing-rewards/rules/request', [
            'name' => 'BTC withdrawal network policy',
            'product' => 'WITHDRAWAL',
            'operation' => 'FEE',
            'fee_type' => 'HYBRID',
            'fixed_value' => '0.00005',
            'percentage_bps' => '5',
            'asset' => 'BTC',
            'currency' => 'BTC',
            'precedence_scope' => 'PRODUCT_DEFAULT',
            'reason' => 'Set approved BTC withdrawal policy.',
        ])->assertAccepted()->json('data');

        $this->assertSame('PENDING_APPROVAL', $change['status']);
        $this->actingAs($maker)->postJson("/api/admin/v1/pricing-rewards/rules/changes/{$change['change_uuid']}/approve", [
            'reason' => 'Maker should not approve own change.',
        ])->assertServerError();

        $approved = $this->actingAs($checker)->postJson("/api/admin/v1/pricing-rewards/rules/changes/{$change['change_uuid']}/approve", [
            'reason' => 'Checker approves tested commercial policy.',
        ])->assertCreated()->json('data');

        $this->assertSame('WITHDRAWAL', $approved['product']);
        $this->assertSame('ACTIVE', $approved['status']);
        $this->assertSame(1, PricingRuleChange::query()->where('status', 'APPROVED')->count());
    }

    public function test_fee_calculator_uses_engine_when_policy_exists_and_records_shadow_comparison(): void
    {
        config(['fees.spot.taker_bps' => '20']);
        $this->pricingRule(['percentage_bps' => '10']);

        $quote = app(FeeCalculator::class)->spot('1000', 'USDT', 'taker');

        $this->assertSame('1.000000000000000000', $quote['fee_amount']);
        $this->assertSame('999.0000000', substr($quote['net_amount'], 0, 11));
        $this->assertTrue($quote['pricing_engine']);
        $this->assertDatabaseHas('pricing_shadow_comparisons', [
            'product' => 'SPOT',
            'operation' => 'TAKER_FEE',
            'status' => 'DIFFERENCE',
        ]);
    }

    public function test_reward_policy_caps_decisions_and_no_float_fallback(): void
    {
        $user = User::factory()->create();
        RewardPolicyRule::query()->create([
            'rule_uuid' => (string) Str::uuid(),
            'name' => 'Referral revenue share',
            'product' => 'REWARDS',
            'operation' => 'REFERRAL_TRADE',
            'reward_type' => 'PERCENTAGE',
            'value' => '0',
            'percentage_bps' => '100',
            'daily_user_cap' => '15',
            'reward_asset' => 'EXAPOINT',
            'priority' => 10,
            'version' => 1,
            'status' => 'ACTIVE',
        ]);

        $first = app(RewardPolicyEngine::class)->decide($user, [
            'product' => 'REWARDS',
            'operation' => 'REFERRAL_TRADE',
            'amount' => '1000',
        ]);
        $second = app(RewardPolicyEngine::class)->decide($user, [
            'product' => 'REWARDS',
            'operation' => 'REFERRAL_TRADE',
            'amount' => '1000',
        ]);

        $this->assertSame('APPROVED', $first->status);
        $this->assertSame('10.000000000000000000', (string) $first->reward_amount);
        $this->assertSame('BLOCKED', $second->status);
        $this->assertSame('DAILY_REWARD_CAP_EXCEEDED', $second->reason_code);
        $this->assertSame(2, RewardPolicyDecision::query()->count());
    }

    public function test_seeded_product_rules_enable_product_wide_central_enforcement(): void
    {
        config([
            'pricing.shadow_mode' => false,
            'pricing.enforced_products' => ['SPOT', 'FUTURES', 'WITHDRAWAL', 'CONVERT', 'FIAT', 'P2P', 'STAKING', 'EXAAI', 'INSTITUTIONAL', 'OTC', 'MARKET_MAKER', 'AFFILIATE', 'REFERRAL'],
            'fees.spot.maker_bps' => '10',
            'fees.spot.taker_bps' => '20',
            'fees.futures.maker_bps' => '2',
            'fees.futures.taker_bps' => '5',
            'fees.withdrawal.bps.USDT' => '10',
            'fees.withdrawal.fixed.USDT' => '1',
            'fees.fiat_deposit.bps.NGN' => '150',
            'fees.fiat_deposit.fixed.NGN' => '0',
            'swap.fee_percent' => '0.5',
            'p2p.fees.taker' => '0.001',
            'custody.fees.default_network_fee' => '0.5',
            'custody.fees.default_platform_fee' => '0.1',
        ]);

        $seed = app(PricingProductMigrationService::class)->seedFromLegacyConfig();
        $this->assertContains('SPOT', $seed['products']);
        $this->assertContains('P2P', $seed['products']);

        $fees = app(FeeCalculator::class);
        $this->assertSame('2.000000000000000000', $fees->spot('1000', 'USDT', 'taker')['fee_amount']);
        $this->assertSame('0.500000000000000000', $fees->futures('1000', 'USDT', 'taker')['fee_amount']);
        $this->assertSame('1.100000000000000000', $fees->withdrawal('100', 'USDT')['fee_amount']);
        $this->assertSame('15.000000000000000000', $fees->fiatDeposit('1000', 'NGN')['fee_amount']);

        $p2p = app(P2PFeeService::class)->quote('BTC', '1000', 'taker');
        $this->assertSame('1.000000000000000000', $p2p['fee_amount']);
        $this->assertSame('PRICING_ENGINE', $p2p['pricing_decision']['source']);

        $custody = app(WithdrawalFeeService::class)->quote('BTC', 'bitcoin', '100');
        $this->assertSame('0.500000000000000000', $custody['network_fee']);
        $this->assertSame('0.100000000000000000', $custody['platform_fee']);

        $convert = app(PricingPolicyEngine::class)->preview([
            'product' => 'CONVERT',
            'operation' => 'FEE',
            'amount' => '1000',
            'asset' => 'USDT',
        ]);
        $this->assertSame('5.000000000000000000', $convert['fee_amount']);

        foreach (['STAKING', 'EXAAI', 'INSTITUTIONAL', 'OTC', 'MARKET_MAKER', 'AFFILIATE', 'REFERRAL'] as $product) {
            $this->assertDatabaseHas('pricing_rules', ['product' => $product, 'status' => 'ACTIVE']);
        }
    }

    public function test_enforced_products_fail_closed_without_central_rule_and_cache_invalidation_applies_updates(): void
    {
        config(['pricing.enforced_products' => ['SPOT'], 'pricing.shadow_mode' => false]);

        $this->expectException(RuntimeException::class);
        app(FeeCalculator::class)->spot('100', 'USDT', 'taker');
    }

    public function test_cache_invalidation_changes_active_pricing_rule(): void
    {
        config(['pricing.enforced_products' => ['SPOT'], 'pricing.shadow_mode' => false]);
        $first = $this->pricingRule(['percentage_bps' => '20']);
        $engine = app(PricingPolicyEngine::class);
        $this->assertSame('2.000000000000000000', $engine->preview(['product' => 'SPOT', 'operation' => 'TAKER_FEE', 'amount' => '1000'])['fee_amount']);

        $first->forceFill(['status' => 'DISABLED'])->save();
        $this->pricingRule(['percentage_bps' => '10', 'priority' => 5]);
        $engine->invalidateCache();

        $this->assertSame('1.000000000000000000', $engine->preview(['product' => 'SPOT', 'operation' => 'TAKER_FEE', 'amount' => '1000'])['fee_amount']);
    }

    private function pricingRule(array $overrides = []): PricingRule
    {
        Cache::forget('pricing_rules.active');

        return PricingRule::query()->create(array_merge([
            'rule_uuid' => (string) Str::uuid(),
            'name' => 'Spot taker default',
            'product' => 'SPOT',
            'operation' => 'TAKER_FEE',
            'fee_type' => 'PERCENTAGE',
            'value' => '0',
            'fixed_value' => '0',
            'percentage_bps' => '20',
            'spread_bps' => '0',
            'precedence_scope' => 'PRODUCT_DEFAULT',
            'priority' => 0,
            'version' => 1,
            'status' => 'ACTIVE',
            'allow_negative' => false,
            'requires_maker_checker' => true,
        ], $overrides));
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);
        $permissions = ['finance.view', 'finance.reconcile', 'finance.adjust.request', 'finance.adjust.approve'];
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }
        app(PermissionService::class)->syncRolePermissions($role, $permissions);

        return Admin::query()->create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
