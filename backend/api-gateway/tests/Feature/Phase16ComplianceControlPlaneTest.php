<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ComplianceDecisionLog;
use App\Models\ComplianceJurisdiction;
use App\Models\CompliancePolicyChange;
use App\Models\CompliancePolicyException;
use App\Models\CompliancePolicyRule;
use App\Models\ComplianceUserRestriction;
use App\Models\FuturesMarket;
use App\Models\Role;
use App\Models\TradingRiskLimit;
use App\Models\TradingUserRiskProfile;
use App\Models\User;
use App\Services\CompliancePolicyAdminService;
use App\Services\CompliancePolicyService;
use App\Services\TradingRiskEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Phase16ComplianceControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
    }

    public function test_jurisdiction_and_kyc_policy_is_authoritative_and_logged(): void
    {
        $this->supportedJurisdiction('NG');
        $user = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 0]);
        $service = app(CompliancePolicyService::class);

        $decision = $service->decide($user, 'SPOT', ['action' => 'BUY', 'market_symbol' => 'BTC/USDT']);
        $this->assertSame('REQUIRE_KYC', $decision['decision']);
        $this->assertSame('KYC_TIER_INSUFFICIENT', $decision['reason_code']);

        $user->forceFill(['kyc_level' => 1])->save();
        $decision = $service->decide($user->fresh(), 'SPOT', ['action' => 'BUY', 'market_symbol' => 'BTC/USDT']);
        $this->assertSame('ALLOW', $decision['decision']);
        $this->assertSame('KYC_REQUIREMENT_SATISFIED', $decision['reason_code']);
        $this->assertSame('SPOT', $decision['product_code']);
        $this->assertSame('NG', $decision['jurisdiction']);
        $this->assertDatabaseCount('compliance_decision_logs', 2);
        $this->assertTrue(ComplianceDecisionLog::query()->where('product_code', 'SPOT')->where('jurisdiction', 'NG')->exists());
    }

    public function test_high_risk_unconfigured_and_blocked_jurisdictions_fail_closed(): void
    {
        $this->supportedJurisdiction('NG');
        $blocked = User::factory()->create(['verified_country' => 'US', 'kyc_level' => 5]);
        $unknown = User::factory()->create(['verified_country' => 'ZZ', 'kyc_level' => 5]);
        ComplianceJurisdiction::query()->create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'status' => 'BLOCKED',
            'risk_level' => 'BLOCKED',
            'policy_version' => 'phase16-test',
        ]);

        $service = app(CompliancePolicyService::class);
        $this->assertSame('DENY', $service->decide($unknown, 'FUTURES', ['action' => 'BUY'])['decision']);
        $this->assertSame('JURISDICTION_UNCONFIGURED_FAIL_CLOSED', $service->decide($unknown, 'FUTURES', ['action' => 'BUY'])['reason_code']);
        $this->assertSame('DENY', $service->decide($blocked, 'SPOT', ['action' => 'BUY'])['decision']);
        $this->assertSame('JURISDICTION_BLOCKED', $service->decide($blocked, 'SPOT', ['action' => 'BUY'])['reason_code']);
    }

    public function test_restrictive_policy_precedence_allows_only_risk_reducing_and_sell_only_actions(): void
    {
        $this->supportedJurisdiction('NG');
        $user = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 3]);
        $this->rule('FUTURES', 'ALLOW', 'FUTURES_NG_ALLOWED', ['jurisdiction' => 'NG', 'max_leverage' => 20, 'precedence' => 10]);
        $this->rule('FUTURES', 'REDUCE_ONLY', 'FUTURES_NG_REDUCE_ONLY', ['jurisdiction' => 'NG', 'max_leverage' => 5, 'precedence' => 100]);
        $this->rule('SPOT', 'SELL_ONLY', 'BTC_SELL_ONLY', ['jurisdiction' => 'NG', 'asset' => 'BTC', 'precedence' => 200]);

        $service = app(CompliancePolicyService::class);
        $blockedOpen = $service->decide($user, 'FUTURES', ['action' => 'BUY', 'market_symbol' => 'BTC/USDT', 'requested_leverage' => 10]);
        $this->assertSame('REDUCE_ONLY', $blockedOpen['decision']);
        $this->assertSame(5, $blockedOpen['effective_max_leverage']);

        $allowedClose = $service->decide($user, 'FUTURES', ['action' => 'REDUCE', 'market_symbol' => 'BTC/USDT', 'requested_leverage' => 10]);
        $this->assertSame('ALLOW', $allowedClose['decision']);
        $this->assertSame('RISK_REDUCING_ACTION_ALLOWED', $allowedClose['reason_code']);

        $buy = $service->decide($user, 'SPOT', ['action' => 'BUY', 'asset' => 'BTC']);
        $sell = $service->decide($user, 'SPOT', ['action' => 'SELL', 'asset' => 'BTC']);
        $this->assertSame('SELL_ONLY', $buy['decision']);
        $this->assertSame('ALLOW', $sell['decision']);
        $this->assertSame('SELL_ONLY_ACTION_ALLOWED', $sell['reason_code']);
    }

    public function test_user_restrictions_and_approved_exceptions_are_enforced(): void
    {
        $this->supportedJurisdiction('NG');
        $user = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 3]);
        ComplianceUserRestriction::query()->create([
            'restriction_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'restriction_type' => 'P2P_DISABLED',
            'status' => 'ACTIVE',
            'reason_code' => 'P2P_RISK_REVIEW',
            'effective_from' => now()->subMinute(),
        ]);

        $service = app(CompliancePolicyService::class);
        $this->assertSame('DENY', $service->decide($user, 'P2P', ['action' => 'CREATE_AD'])['decision']);

        CompliancePolicyException::query()->create([
            'exception_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'product_code' => 'P2P',
            'decision' => 'ALLOW',
            'status' => 'ACTIVE',
            'reason' => 'Compliance reviewer allowed controlled P2P access.',
            'effective_from' => now()->subMinute(),
            'effective_to' => now()->addHour(),
        ]);

        $allowed = $service->decide($user, 'P2P', ['action' => 'CREATE_AD']);
        $this->assertSame('ALLOW', $allowed['decision']);
        $this->assertSame('APPROVED_EXCEPTION', $allowed['reason_code']);
        $this->assertStringStartsWith('EXCEPTION:', $allowed['policy_source']);
    }

    public function test_admin_policy_changes_require_maker_checker_and_user_eligibility_api_returns_policy_snapshot(): void
    {
        $this->supportedJurisdiction('NG');
        $maker = $this->admin('maker@example.com');
        $checker = $this->admin('checker@example.com');
        $user = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 3]);
        $service = app(CompliancePolicyAdminService::class);

        $change = $service->submitRuleChange($maker, [
            'name' => 'Nigeria futures limited production',
            'jurisdiction' => 'NG',
            'product_code' => 'FUTURES',
            'decision' => 'ALLOW',
            'reason_code' => 'NG_FUTURES_LIMITED',
            'required_kyc_level' => 3,
            'max_leverage' => 3,
            'precedence' => 300,
            'reason' => 'Controlled phase sixteen futures launch.',
        ]);

        $this->expectException(RuntimeException::class);
        try {
            $service->approveChange($maker, $change, 'same admin cannot approve');
        } finally {
            $rule = $service->approveChange($checker, $change->fresh(), 'Second admin approved controlled launch.');
            $this->assertSame('ACTIVE', $rule->status);
            $this->assertSame('APPROVED', CompliancePolicyChange::query()->whereKey($change->id)->value('status'));
        }

        $response = $this->actingAs($user)->getJson('/api/me/eligibility')->assertOk()->json('data.products.FUTURES');
        $this->assertSame('ALLOW', $response['decision']);
        $this->assertSame(3, $response['effective_max_leverage']);
    }

    public function test_trading_risk_engine_enforces_compliance_leverage_and_blocks_denied_products(): void
    {
        $this->supportedJurisdiction('NG');
        $user = User::factory()->create(['verified_country' => 'NG', 'kyc_level' => 3]);
        TradingUserRiskProfile::query()->create([
            'user_id' => $user->id,
            'risk_tier' => 'DEFAULT',
            'trading_enabled' => true,
            'margin_enabled' => true,
            'futures_enabled' => true,
            'status' => 'ACTIVE',
        ]);
        TradingRiskLimit::query()->create([
            'limit_id' => (string) Str::uuid(),
            'scope' => 'DEFAULT',
            'product' => 'futures',
            'max_order_notional' => '1000000',
            'max_daily_notional' => '10000000',
            'max_open_orders' => 100,
            'max_leverage' => 50,
            'status' => 'ACTIVE',
        ]);
        $this->rule('FUTURES', 'ALLOW', 'NG_FUTURES_KYC3', ['jurisdiction' => 'NG', 'required_kyc_level' => 3, 'max_leverage' => 2, 'precedence' => 500]);
        $market = FuturesMarket::query()->create([
            'symbol' => 'BTC/USDT',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'status' => 'TRADING',
            'trading_status' => 'TRADING',
            'mark_price' => '50000',
            'index_price' => '50000',
            'max_leverage' => 50,
            'min_order_size' => '0.001',
            'max_order_size' => '100',
            'tick_size' => '0.1',
            'step_size' => '0.001',
            'contract_size' => '1',
            'metadata' => [],
        ]);

        $risk = app(TradingRiskEngine::class);
        $over = $risk->evaluateOrder($user->id, 'futures', $market, ['side' => 'buy', 'type' => 'limit', 'quantity' => '0.01', 'price' => '50000', 'leverage' => 5]);
        $this->assertSame('REJECT', $over['action']);
        $this->assertSame('MAX_LEVERAGE_EXCEEDED', $over['reason_code']);

        CompliancePolicyRule::query()->delete();
        Cache::flush();
        $this->rule('FUTURES', 'DENY', 'FUTURES_PAUSED_BY_COMPLIANCE', ['jurisdiction' => 'NG', 'precedence' => 1000]);
        $denied = $risk->evaluateOrder($user->id, 'futures', $market, ['side' => 'buy', 'type' => 'limit', 'quantity' => '0.01', 'price' => '50000', 'leverage' => 1]);
        $this->assertSame('COMPLIANCE_RESTRICTED', $denied['status']);
        $this->assertSame('FUTURES_PAUSED_BY_COMPLIANCE', $denied['reason_code']);
    }

    private function supportedJurisdiction(string $country): void
    {
        ComplianceJurisdiction::query()->create([
            'country_code' => $country,
            'country_name' => $country,
            'status' => 'SUPPORTED',
            'risk_level' => 'STANDARD',
            'policy_version' => 'phase16-test',
        ]);
    }

    private function rule(string $product, string $decision, string $reason, array $overrides = []): CompliancePolicyRule
    {
        return CompliancePolicyRule::query()->create(array_merge([
            'rule_uuid' => (string) Str::uuid(),
            'product_code' => $product,
            'decision' => $decision,
            'reason_code' => $reason,
            'status' => 'ACTIVE',
            'policy_version' => 'phase16-test',
            'effective_at' => now()->subMinute(),
        ], $overrides));
    }

    private function admin(string $email = 'admin@example.com'): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'Compliance Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
