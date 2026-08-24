<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperProject;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalRole;
use App\Models\InstitutionalSubaccount;
use App\Models\ListingApplication;
use App\Models\ListingAssetConfiguration;
use App\Models\ListingLiquidityRequirement;
use App\Models\ListingMarketConfiguration;
use App\Models\ListingOrganization;
use App\Models\ListingTestRun;
use App\Models\LiquidityAgreement;
use App\Models\Market;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotIncident;
use App\Models\MarketMakerBotStrategy;
use App\Models\MarketMakerBotStrategyVersion;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\OtcMarketConfig;
use App\Models\Phase15EmergencyControl;
use App\Models\Phase15ReconciliationDifference;
use App\Models\Role;
use App\Models\User;
use App\Services\InstitutionalService;
use App\Services\MarketLaunchReadinessService;
use App\Services\MarketMakerBotRiskService;
use App\Services\Phase15EmergencyControlService;
use App\Services\Phase15ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Phase15FInstitutionalLiquidityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false, 'trading.engine.mode' => 'legacy']);
    }

    public function test_listing_liquidity_readiness_reconciliation_emergency_and_api_key_isolation(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        [$institution, $subaccount, $profile] = $this->marketMakerInstitution($owner);
        [$application, $market, $marketConfig, $requirement] = $this->listingStack($owner, $admin);

        MarketMakerMarketAssignment::query()->create([
            'assignment_uuid' => (string) Str::uuid(),
            'market_maker_id' => $profile->id,
            'market_id' => $market->id,
            'market_symbol' => 'ATLAS/USDT',
            'status' => 'ACTIVE',
            'minimum_depth' => '500',
            'maximum_spread_bps' => '80',
            'minimum_quote_presence' => '95',
            'target_quote_size' => '100',
            'maximum_inventory' => '1000000',
            'listing_liquidity_requirement_id' => $requirement->id,
        ]);
        LiquidityAgreement::query()->create([
            'agreement_uuid' => (string) Str::uuid(),
            'market_maker_id' => $profile->id,
            'institution_id' => $institution->id,
            'subaccount_id' => $subaccount->id,
            'market_symbol' => 'ATLAS/USDT',
            'base_asset' => 'ATLAS',
            'quote_asset' => 'USDT',
            'base_commitment' => '1000',
            'quote_commitment' => '50000',
            'status' => 'ACTIVE',
            'effective_at' => now(),
            'approved_by_admin_id' => $admin->id,
        ]);

        $readiness = app(MarketLaunchReadinessService::class)->evaluate($application);
        $this->assertSame('BLOCKED', $readiness['status']);
        $this->assertContains('MM_CAPITAL_NOT_READY', $readiness['blockers']);

        app(InstitutionalService::class)->adminCreditSubaccount($admin, $subaccount, 'ATLAS', '1500', 'Fund listing base liquidity.');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $subaccount, 'USDT', '60000', 'Fund listing quote liquidity.');

        $bot = $this->bot($institution, $subaccount, $profile, 'ATLAS/USDT');
        $readiness = app(MarketLaunchReadinessService::class)->evaluate($application->fresh());
        $this->assertSame('READY', $readiness['status']);
        $this->assertSame([], $readiness['blockers']);
        $this->assertTrue($readiness['markets'][0]['capital_ready']);
        $this->assertTrue($readiness['markets'][0]['bot_ready']);
        $this->assertTrue($readiness['no_unsafe_auto_launch']);

        $market->forceFill(['status' => 'active', 'trading_status' => 'TRADING'])->save();
        $apiKey = $this->revokedApiKey($owner, $institution, $subaccount);
        $bot->forceFill(['api_key_id' => $apiKey->id])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEVELOPER_API_KEY_NOT_ACTIVE');
        try {
            app(MarketMakerBotRiskService::class)->assertCanQuote($bot->fresh(), ['market_data_status' => 'FRESH', 'age_seconds' => 0], ['status' => 'HEALTHY']);
        } finally {
            $this->assertSame(1, MarketMakerBotIncident::query()->where('category', 'BOT_RISK_BLOCK')->count());
        }
    }

    public function test_phase15_reconciliation_and_emergency_controls_propagate_to_market_liquidity_stack(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin('phase15f-emergency@example.com');
        [$institution, $subaccount, $profile] = $this->marketMakerInstitution($owner);
        [$application, $market] = $this->listingStack($owner, $admin);
        unset($application);

        MarketMakerMarketAssignment::query()->create([
            'assignment_uuid' => (string) Str::uuid(),
            'market_maker_id' => $profile->id,
            'market_id' => $market->id,
            'market_symbol' => 'ATLAS/USDT',
            'status' => 'ACTIVE',
        ]);
        $bot = $this->bot($institution, $subaccount, $profile, 'ATLAS/USDT');
        OtcMarketConfig::query()->create([
            'config_uuid' => (string) Str::uuid(),
            'symbol' => 'ATLASUSDT',
            'base_asset' => 'ATLAS',
            'quote_asset' => 'USDT',
            'enabled' => true,
        ]);

        $profile->forceFill(['status' => 'PAUSED'])->save();
        $run = app(Phase15ReconciliationService::class)->run('PHASE15F_FOCUSED');
        $this->assertSame('CRITICAL_BREAKS_FOUND', $run->status);
        $this->assertDatabaseHas('phase15_reconciliation_differences', [
            'run_id' => $run->id,
            'code' => 'ACTIVE_BOT_WITHOUT_ACTIVE_MM',
            'severity' => 'CRITICAL',
        ]);
        $this->assertGreaterThanOrEqual(1, Phase15ReconciliationDifference::query()->where('run_id', $run->id)->count());

        app(Phase15EmergencyControlService::class)->activate(
            $admin,
            'MARKET',
            'ATLAS/USDT',
            'GLOBAL_LIQUIDITY_EMERGENCY',
            'Phase 15F market halt drill.'
        );

        $this->assertSame('halted', $market->fresh()->status);
        $this->assertSame('PAUSED', $bot->fresh()->status);
        $this->assertFalse((bool) OtcMarketConfig::query()->where('symbol', 'ATLASUSDT')->value('enabled'));
        $this->assertSame(1, Phase15EmergencyControl::query()->where('control', 'GLOBAL_LIQUIDITY_EMERGENCY')->count());
    }

    private function listingStack(User $owner, Admin $admin): array
    {
        $organization = ListingOrganization::query()->create([
            'organization_uuid' => (string) Str::uuid(),
            'owner_user_id' => $owner->id,
            'legal_name' => 'Atlas Labs Ltd',
            'project_name' => 'Atlas',
            'jurisdiction' => 'NG',
            'status' => 'ACTIVE',
        ]);
        $application = ListingApplication::query()->create([
            'application_uuid' => (string) Str::uuid(),
            'reference' => 'LIST-ATLAS-'.Str::upper(Str::random(6)),
            'organization_id' => $organization->id,
            'submitted_by' => $owner->id,
            'application_type' => 'TOKEN_LISTING',
            'application_status' => 'APPROVED',
            'integration_status' => 'PRE_LAUNCH',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);
        ListingAssetConfiguration::query()->create([
            'asset_config_uuid' => (string) Str::uuid(),
            'application_id' => $application->id,
            'asset_uid' => 'ATLAS-'.Str::lower(Str::random(6)),
            'name' => 'Atlas',
            'symbol' => 'ATLAS',
            'slug' => 'atlas-'.Str::lower(Str::random(6)),
            'asset_type' => 'TOKEN',
            'network' => 'BSC',
            'token_standard' => 'BEP20',
            'decimals' => 18,
            'status' => 'CONFIGURED',
            'trading_enabled' => true,
        ]);
        $market = Market::query()->create([
            'symbol' => 'ATLAS/USDT',
            'base_currency' => 'ATLAS',
            'quote_currency' => 'USDT',
            'status' => 'pre_launch',
            'trading_status' => 'PRE_LAUNCH',
            'last_price' => '1',
            'price_precision' => '0.00010000',
            'tick_size' => '0.000100000000000000',
            'quantity_step' => '0.010000000000000000',
            'min_order_size' => '1.00000000',
            'min_notional' => '5.000000000000000000',
            'maker_fee' => '0.00100000',
            'taker_fee' => '0.00200000',
        ]);
        $marketConfig = ListingMarketConfiguration::query()->create([
            'market_config_uuid' => (string) Str::uuid(),
            'application_id' => $application->id,
            'market_id' => $market->id,
            'symbol' => 'ATLAS/USDT',
            'base_asset' => 'ATLAS',
            'quote_asset' => 'USDT',
            'status' => 'PRE_LAUNCH',
        ]);
        $requirement = ListingLiquidityRequirement::query()->create([
            'application_id' => $application->id,
            'listing_market_configuration_id' => $marketConfig->id,
            'arrangement' => 'MARKET_MAKER',
            'required_base_liquidity' => '1000',
            'required_quote_liquidity' => '50000',
            'maximum_spread_bps' => '80',
            'minimum_depth' => '500',
            'liquidity_status' => 'READY_FOR_PREFUNDING',
        ]);
        ListingTestRun::query()->create([
            'test_run_uuid' => (string) Str::uuid(),
            'application_id' => $application->id,
            'requested_by_admin_id' => $admin->id,
            'environment' => 'staging',
            'overall_status' => 'PASS',
            'results' => ['asset_config' => 'PASS', 'market_config' => 'PASS', 'custody' => 'PASS'],
            'completed_at' => now(),
        ]);

        return [$application, $market, $marketConfig, $requirement];
    }

    private function marketMakerInstitution(User $owner): array
    {
        $institution = InstitutionalAccount::query()->create([
            'institution_uuid' => (string) Str::uuid(),
            'master_user_id' => $owner->id,
            'legal_name' => 'Atlas Liquidity Desk',
            'country_of_incorporation' => 'NG',
            'business_type' => 'INSTITUTION',
            'status' => 'ACTIVE',
            'kyb_status' => 'APPROVED',
            'compliance_status' => 'APPROVED',
            'capability_flags' => ['market_maker' => true, 'otc' => true],
        ]);
        $role = InstitutionalRole::query()->create([
            'institution_id' => $institution->id,
            'name' => 'OWNER',
            'role_type' => 'OWNER',
            'permissions' => InstitutionalService::OWNER_PERMISSIONS,
            'system_template' => true,
        ]);
        InstitutionalMembership::query()->create([
            'membership_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'status' => 'ACTIVE',
            'accepted_at' => now(),
        ]);
        $subaccount = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'name' => 'Listing Liquidity',
            'type' => 'MARKET_MAKER',
            'status' => 'ACTIVE',
            'risk_mode' => 'ISOLATED',
            'product_flags' => ['SPOT' => true, 'OTC' => false, 'MM_BOT' => true],
        ]);
        $profile = MarketMakerProfile::query()->create([
            'profile_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'subaccount_id' => $subaccount->id,
            'status' => 'ACTIVE',
            'provider_type' => 'INSTITUTIONAL_MARKET_MAKER',
            'rate_profile' => 'MARKET_MAKER_STANDARD',
            'safety_mode' => 'NORMAL',
            'approved_markets' => ['ATLAS/USDT'],
            'limits' => ['max_order_rate_per_second' => 50, 'max_open_orders' => 5000],
        ]);

        return [$institution, $subaccount, $profile];
    }

    private function bot(InstitutionalAccount $institution, InstitutionalSubaccount $subaccount, MarketMakerProfile $profile, string $symbol): MarketMakerBot
    {
        $strategy = MarketMakerBotStrategy::query()->create([
            'strategy_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'market_maker_id' => $profile->id,
            'name' => 'Atlas Two-Sided',
            'strategy_type' => 'TWO_SIDED_MARKET_MAKING',
            'status' => 'APPROVED',
            'supported_markets' => [$symbol],
        ]);
        $version = MarketMakerBotStrategyVersion::query()->create([
            'version_uuid' => (string) Str::uuid(),
            'strategy_id' => $strategy->id,
            'version' => 1,
            'status' => 'APPROVED',
            'parameters' => ['quote_size' => '100', 'base_spread_bps' => '30'],
            'supported_markets' => [$symbol],
        ]);

        return MarketMakerBot::query()->create([
            'bot_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'market_maker_id' => $profile->id,
            'subaccount_id' => $subaccount->id,
            'strategy_id' => $strategy->id,
            'strategy_version_id' => $version->id,
            'name' => 'Atlas Listing Bot',
            'market_symbol' => $symbol,
            'product_type' => 'SPOT',
            'status' => 'ACTIVE',
            'safety_state' => 'NORMAL',
            'configuration' => ['quote_size' => '100', 'levels' => 2],
            'risk_limits' => ['max_market_data_age_seconds' => 120],
            'approved_at' => now(),
        ]);
    }

    private function revokedApiKey(User $owner, InstitutionalAccount $institution, InstitutionalSubaccount $subaccount): DeveloperApiKey
    {
        $project = DeveloperProject::query()->create([
            'project_uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'name' => 'Atlas API',
            'environment' => 'production',
            'status' => 'active',
        ]);

        return DeveloperApiKey::query()->create([
            'key_uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'project_id' => $project->id,
            'institution_id' => $institution->id,
            'subaccount_id' => $subaccount->id,
            'name' => 'Revoked MM key',
            'environment' => 'production',
            'rate_profile' => 'MARKET_MAKER',
            'key_prefix' => 'exa_live_revoked',
            'key_hash' => hash('sha256', Str::random(32)),
            'encrypted_secret' => encrypt(Str::random(48)),
            'secret_hash' => hash('sha256', Str::random(32)),
            'status' => 'REVOKED',
        ]);
    }

    private function admin(string $email = 'phase15f-admin@example.com'): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'Phase 15F Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
