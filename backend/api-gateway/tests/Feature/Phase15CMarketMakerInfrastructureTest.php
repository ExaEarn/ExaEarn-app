<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeveloperApiKey;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalRole;
use App\Models\InstitutionalSubaccount;
use App\Models\LedgerEntry;
use App\Models\Market;
use App\Models\MarketMakerAccount;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerProgramApplication;
use App\Models\MarketMakerQuote;
use App\Models\Role;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\InstitutionalService;
use App\Services\MarketLiquidityHealthService;
use App\Services\MarketMakerInventoryService;
use App\Services\MarketMakerProgramService;
use App\Services\MarketMakerRebateService;
use App\Services\MarketMakerSurveillanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase15CMarketMakerInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
    }

    public function test_market_maker_program_operations_capital_rebates_surveillance_and_controls(): void
    {
        $owner = User::factory()->create();
        $maker = $this->admin('mm-maker@example.com');
        $checker = $this->admin('mm-checker@example.com');
        $market = $this->market('BTCUSDT', 'BTC', 'USDT');
        [$institution, $subaccount] = $this->institution($owner, 'Atlas MM Desk');

        app(InstitutionalService::class)->adminCreditSubaccount($checker, $subaccount, 'BTC', '5', 'Seed base inventory.');
        app(InstitutionalService::class)->adminCreditSubaccount($checker, $subaccount, 'USDT', '250000', 'Seed quote inventory.');

        $application = $this->actingAs($owner)->postJson('/api/institutional/market-making/apply', [
            'subaccount_id' => $subaccount->id,
            'requested_markets' => ['BTCUSDT'],
            'requested_products' => ['SPOT'],
            'technical_profile' => ['websocket' => true, 'mass_cancel' => true],
            'risk_profile' => ['max_notional_per_market' => '500000'],
            'commercial_terms' => ['maker_rebate_bps' => '1'],
            'idempotency_key' => 'mm-apply-atlas-btc',
        ])->assertCreated()->json('data');
        $this->assertSame('PENDING_TECHNICAL_REVIEW', $application['status']);

        $duplicate = $this->actingAs($owner)->postJson('/api/institutional/market-making/apply', [
            'subaccount_id' => $subaccount->id,
            'requested_markets' => ['BTCUSDT'],
            'requested_products' => ['SPOT'],
            'idempotency_key' => 'mm-apply-atlas-btc',
        ])->assertCreated()->json('data');
        $this->assertSame($application['application_uuid'], $duplicate['application_uuid']);
        $this->assertSame(1, MarketMakerProgramApplication::query()->where('idempotency_key', 'mm-apply-atlas-btc')->count());

        foreach (['TECHNICAL_REVIEW', 'RISK_REVIEW', 'COMMERCIAL_REVIEW', 'APPROVED'] as $status) {
            $this->actingAs($maker)->postJson("/api/admin/v1/market-makers/applications/{$application['application_uuid']}/transition", [
                'status' => $status,
                'reason' => "Move to {$status}.",
            ])->assertOk();
        }

        $this->actingAs($maker)->postJson("/api/admin/v1/market-makers/applications/{$application['application_uuid']}/activate", [
            'reason' => 'Maker cannot activate own market-maker approval.',
        ])->assertStatus(422);

        $profile = $this->actingAs($checker)->postJson("/api/admin/v1/market-makers/applications/{$application['application_uuid']}/activate", [
            'reason' => 'Checker activates approved market-maker profile.',
        ])->assertCreated()->json('data');
        $this->assertSame('ACTIVE', $profile['status']);
        $this->assertSame('MARKET_MAKER_STANDARD', $profile['rate_profile']);

        $assignment = $this->actingAs($checker)->postJson("/api/admin/v1/market-makers/profiles/{$profile['profile_uuid']}/assignments", [
            'market_symbol' => 'BTCUSDT',
            'minimum_depth' => '1',
            'maximum_spread_bps' => '50',
            'minimum_quote_presence' => '95',
            'target_quote_size' => '50000',
            'maximum_inventory' => '500000',
            'rebate_profile' => ['maker_rebate_bps' => '1'],
            'reason' => 'Launch BTCUSDT liquidity obligation.',
        ])->assertCreated()->json('data');
        $this->assertSame('BTCUSDT', $assignment['market_symbol']);

        $this->actingAs($checker)->postJson("/api/admin/v1/market-makers/profiles/{$profile['profile_uuid']}/agreements", [
            'market_symbol' => 'BTCUSDT',
            'base_commitment' => '2',
            'quote_commitment' => '100000',
            'spread_requirement_bps' => '50',
            'depth_requirement' => '1',
            'quote_presence_requirement' => '95',
            'reason' => 'Listing liquidity agreement for BTCUSDT.',
        ])->assertCreated();

        $capital = app(MarketMakerProgramService::class)->capitalReadiness(MarketMakerProfile::query()->firstOrFail(), 'BTCUSDT');
        $this->assertSame('READY', $capital['status']);
        $this->assertSame('5.000000000000000000', $capital['base_available']);
        $this->assertSame('250000.000000000000000000', $capital['quote_available']);

        $snapshot = app(MarketMakerInventoryService::class)->snapshot(MarketMakerProfile::query()->firstOrFail(), 'BTCUSDT');
        $this->assertSame('HEALTHY', $snapshot->status);
        $this->assertSame('canonical_institutional_subaccount_ledger', $snapshot->metadata['source']);

        $legacyAccount = MarketMakerAccount::query()->create([
            'market_maker_id' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'name' => 'Atlas Phase15C MM',
            'account_type' => 'INSTITUTIONAL',
            'status' => 'ACTIVE',
            'permissions' => ['quote'],
            'limits' => [],
            'metadata' => ['phase15c_profile_id' => MarketMakerProfile::query()->firstOrFail()->id],
        ]);
        MarketMakerQuote::query()->create([
            'quote_id' => (string) Str::uuid(),
            'market_maker_account_id' => $legacyAccount->id,
            'market_symbol' => $market->symbol,
            'side' => 'BUY',
            'price' => '49900',
            'quantity' => '1.5',
            'reserved_inventory' => '74850',
            'status' => 'ACTIVE',
            'metadata' => ['phase15c_profile_id' => MarketMakerProfile::query()->firstOrFail()->id],
        ]);
        MarketMakerQuote::query()->create([
            'quote_id' => (string) Str::uuid(),
            'market_maker_account_id' => $legacyAccount->id,
            'market_symbol' => $market->symbol,
            'side' => 'SELL',
            'price' => '50100',
            'quantity' => '1.5',
            'reserved_inventory' => '1.5',
            'status' => 'ACTIVE',
            'metadata' => ['phase15c_profile_id' => MarketMakerProfile::query()->firstOrFail()->id],
        ]);
        $health = app(MarketLiquidityHealthService::class)->snapshot('BTCUSDT');
        $this->assertSame('HEALTHY', $health->status);
        $this->assertSame('100.00000000', (string) $health->quote_presence);

        $readiness = app(MarketMakerProgramService::class)->listingReadiness('BTCUSDT');
        $this->assertSame('READY', $readiness['status']);

        $rebate = app(MarketMakerRebateService::class)->accrue(
            MarketMakerProfile::query()->firstOrFail(),
            \App\Models\MarketMakerMarketAssignment::query()->firstOrFail(),
            now()->startOfDay()->toDateTimeString(),
            now()->endOfDay()->toDateTimeString(),
            '100000',
            '1',
            'USDT'
        );
        $paid = app(MarketMakerRebateService::class)->pay($checker, $rebate, 'Settle eligible maker rebate.');
        $again = app(MarketMakerRebateService::class)->pay($checker, $paid, 'Idempotent retry.');
        $this->assertSame('PAID', $again->status);
        $this->assertSame(1, LedgerEntry::query()->where('reference', $paid->settlement_reference)->where('transaction_type', 'market_maker_rebate')->where('amount', '10.000000000000000000')->count());

        [$institution, $secondSubaccount] = $this->addSubaccount($institution, 'Atlas MM Desk 2');
        $secondProfile = MarketMakerProfile::query()->create([
            'profile_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'subaccount_id' => $secondSubaccount->id,
            'status' => 'ACTIVE',
            'provider_type' => 'INSTITUTIONAL_MARKET_MAKER',
            'rate_profile' => 'MARKET_MAKER_STANDARD',
            'safety_mode' => 'NORMAL',
        ]);
        \App\Models\MarketMakerMarketAssignment::query()->create([
            'assignment_uuid' => (string) Str::uuid(),
            'market_maker_id' => $secondProfile->id,
            'market_id' => $market->id,
            'market_symbol' => 'BTCUSDT',
            'status' => 'ACTIVE',
        ]);
        $case = app(MarketMakerSurveillanceService::class)->detectRelatedInstitutionMarketOverlap(MarketMakerProfile::query()->where('profile_uuid', $profile['profile_uuid'])->firstOrFail(), 'BTCUSDT');
        $this->assertSame('RELATED_ACCOUNT_MARKET_OVERLAP', $case?->signal_type);

        $massCancel = $this->actingAs($checker)->postJson("/api/admin/v1/market-makers/profiles/{$profile['profile_uuid']}/mass-cancel", [
            'market_symbol' => 'BTCUSDT',
            'reason' => 'Emergency quote withdrawal test.',
        ])->assertOk()->json('data');
        $this->assertSame(2, $massCancel['cancelled_quotes']);

        $project = app(DeveloperApiKeyService::class)->createProject($owner->id, ['name' => 'MM API', 'environment' => 'sandbox']);
        app(DeveloperApiKeyService::class)->createKey($owner->id, $project, [
            'name' => 'MM trading key',
            'permissions' => ['market.read', 'account.read', 'spot.read', 'spot.trade'],
            'institution_id' => $institution->id,
            'subaccount_id' => $subaccount->id,
            'rate_profile' => 'MARKET_MAKER_STANDARD',
        ]);
        $key = DeveloperApiKey::query()->firstOrFail();
        $this->assertSame('MARKET_MAKER_STANDARD', $key->rate_profile);
        $this->assertDatabaseHas('developer_api_key_permissions', ['api_key_id' => $key->id, 'permission' => 'spot.trade']);
        $this->assertDatabaseMissing('developer_api_key_permissions', ['api_key_id' => $key->id, 'permission' => 'wallet.withdraw']);

        $this->actingAs($checker)->postJson("/api/admin/v1/market-makers/profiles/{$profile['profile_uuid']}/safety-mode", [
            'mode' => 'REDUCE_ONLY',
            'reason' => 'Controlled safety-mode test.',
        ])->assertOk()->assertJsonPath('data.safety_mode', 'REDUCE_ONLY');
    }

    private function institution(User $owner, string $deskName): array
    {
        $institution = InstitutionalAccount::query()->create([
            'institution_uuid' => (string) Str::uuid(),
            'master_user_id' => $owner->id,
            'legal_name' => 'Atlas Liquidity Ltd',
            'country_of_incorporation' => 'NG',
            'business_type' => 'MARKET_MAKER',
            'status' => 'ACTIVE',
            'kyb_status' => 'APPROVED',
            'compliance_status' => 'APPROVED',
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

        return $this->addSubaccount($institution, $deskName);
    }

    private function addSubaccount(InstitutionalAccount $institution, string $name): array
    {
        $subaccount = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'name' => $name,
            'type' => 'MARKET_MAKER',
            'status' => 'ACTIVE',
            'risk_mode' => 'ISOLATED',
            'product_flags' => ['SPOT' => true, 'API' => true],
        ]);

        return [$institution, $subaccount];
    }

    private function market(string $symbol, string $base, string $quote): Market
    {
        return Market::query()->create([
            'symbol' => $symbol,
            'base_currency' => $base,
            'quote_currency' => $quote,
            'status' => 'active',
            'trading_status' => 'TRADING',
            'engine_mode' => 'NEW_ENGINE',
            'liquidity_mode' => 'INTERNAL',
            'price_precision' => 8,
            'quantity_step' => '0.00000001',
            'min_order_size' => '0.0001',
            'min_notional' => '10',
            'maker_fee' => '0.001',
            'taker_fee' => '0.001',
        ]);
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'Liquidity Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
