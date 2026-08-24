<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalRole;
use App\Models\InstitutionalSubaccount;
use App\Models\LedgerEntry;
use App\Models\MarketMakerProfile;
use App\Models\OtcCounterpartyExposure;
use App\Models\OtcLiquidityProvider;
use App\Models\OtcQuote;
use App\Models\OtcRfq;
use App\Models\OtcSettlement;
use App\Models\OtcRiskEvent;
use App\Models\OtcTrade;
use App\Models\Role;
use App\Models\User;
use App\Services\InstitutionalService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase15DOtcRfqInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
    }

    public function test_institutional_otc_rfq_quote_acceptance_internal_mm_settlement_and_reconciliation(): void
    {
        $client = User::factory()->create();
        $retail = User::factory()->create();
        $admin = $this->admin('otc-admin@example.com');
        [$clientInstitution, $clientSubaccount] = $this->institution($client, 'Client OTC Desk');
        [$mmInstitution, $mmSubaccount] = $this->institution(User::factory()->create(), 'MM OTC Desk');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $clientSubaccount, 'USDT', '1000000', 'Seed client OTC quote currency.');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $mmSubaccount, 'BTC', '25', 'Seed MM base inventory.');

        $market = $this->actingAs($admin)->postJson('/api/admin/v1/otc/markets', [
            'symbol' => 'BTCUSDT',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'enabled' => true,
            'minimum_size' => '0.5',
            'maximum_size' => '50',
            'quote_ttl_seconds' => 120,
            'manual_review_threshold' => '20',
            'reason' => 'Enable internal BTCUSDT OTC.',
        ])->assertCreated()->json('data');
        $this->assertSame('BTCUSDT', $market['symbol']);

        $mmProfile = MarketMakerProfile::query()->create([
            'profile_uuid' => (string) Str::uuid(),
            'institution_id' => $mmInstitution->id,
            'subaccount_id' => $mmSubaccount->id,
            'status' => 'ACTIVE',
            'provider_type' => 'INSTITUTIONAL_MARKET_MAKER',
            'rate_profile' => 'MARKET_MAKER_STANDARD',
            'safety_mode' => 'NORMAL',
            'approved_markets' => ['BTCUSDT'],
            'limits' => ['otc_enabled' => true],
        ]);
        $provider = $this->actingAs($admin)->postJson('/api/admin/v1/otc/providers', [
            'provider_type' => 'EXAEARN_MARKET_MAKER',
            'market_maker_id' => $mmProfile->id,
            'institution_id' => $mmInstitution->id,
            'subaccount_id' => $mmSubaccount->id,
            'markets' => ['BTCUSDT'],
            'limits' => ['max_notional' => '1000000'],
            'reason' => 'Explicitly enable MM as OTC LP.',
        ])->assertCreated()->json('data');
        $this->assertSame('EXAEARN_MARKET_MAKER', $provider['provider_type']);

        $this->actingAs($retail)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '1',
            'idempotency_key' => 'retail-rfq',
        ])->assertNotFound();

        $rfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '2',
            'idempotency_key' => 'client-rfq-btc-2',
        ])->assertCreated()->json('data');
        $this->assertSame('QUOTING', $rfq['status']);

        $duplicateRfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '2',
            'idempotency_key' => 'client-rfq-btc-2',
        ])->assertCreated()->json('data');
        $this->assertSame($rfq['rfq_uuid'], $duplicateRfq['rfq_uuid']);

        $quote = $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$rfq['rfq_uuid']}/providers/{$provider['provider_uuid']}/quotes", [
            'price' => '50000',
            'available_base_amount' => '2',
            'client_fee' => '25',
            'ttl_seconds' => 90,
            'provider_reference' => 'mm-btc-2-quote',
        ])->assertCreated()->json('data');
        $this->assertSame('VALID', $quote['status']);

        $trade = $this->actingAs($client)->postJson("/api/institutional/otc/rfqs/{$rfq['rfq_uuid']}/accept", [
            'idempotency_key' => 'accept-btc-2',
        ])->assertCreated()->json('data');
        $this->assertSame('SETTLED', $trade['status']);
        $this->assertSame('100000.000000000000000000', $trade['quote_amount']);

        $duplicateTrade = $this->actingAs($client)->postJson("/api/institutional/otc/rfqs/{$rfq['rfq_uuid']}/accept", [
            'idempotency_key' => 'accept-btc-2',
        ])->assertCreated()->json('data');
        $this->assertSame($trade['trade_uuid'], $duplicateTrade['trade_uuid']);
        $this->assertSame(1, OtcTrade::query()->where('idempotency_key', 'accept-btc-2')->count());

        $clientBtc = app(InstitutionalService::class)->canonicalSubaccountLedgerAccount($clientSubaccount->id, 'BTC')->fresh();
        $clientUsdt = app(InstitutionalService::class)->canonicalSubaccountLedgerAccount($clientSubaccount->id, 'USDT')->fresh();
        $mmBtc = app(InstitutionalService::class)->canonicalSubaccountLedgerAccount($mmSubaccount->id, 'BTC')->fresh();
        $mmUsdt = app(InstitutionalService::class)->canonicalSubaccountLedgerAccount($mmSubaccount->id, 'USDT')->fresh();
        $this->assertSame('2.000000000000000000', (string) $clientBtc->balance);
        $this->assertSame('899975.000000000000000000', (string) $clientUsdt->balance);
        $this->assertSame('23.000000000000000000', (string) $mmBtc->balance);
        $this->assertSame('100000.000000000000000000', (string) $mmUsdt->balance);
        $this->assertSame(1, LedgerEntry::query()->where('reference', $trade['ledger_reference'])->where('transaction_type', 'otc_internal_settlement')->where('asset', 'BTC')->where('amount', '2.000000000000000000')->count());

        $storedRfq = OtcRfq::query()->where('rfq_uuid', $rfq['rfq_uuid'])->firstOrFail();
        $this->assertSame('SETTLED', $storedRfq->status);
        $this->assertTrue((bool) $storedRfq->metadata['public_market_data_isolated']);

        $replay = $this->actingAs($client)->getJson('/api/institutional/realtime/replay?stream='.urlencode("institution.{$clientInstitution->id}.otc").'&after_sequence=0')
            ->assertOk()
            ->json('data');
        $this->assertGreaterThanOrEqual(2, count($replay));

        $reconciliation = $this->actingAs($admin)->postJson('/api/admin/v1/otc/reconciliation')->assertCreated()->json('data');
        $this->assertSame('PASS', $reconciliation['status']);

        $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '200',
            'idempotency_key' => 'client-rfq-too-large',
        ])->assertStatus(422);

        $outsider = User::factory()->create();
        [$otherInstitution, $otherSubaccount] = $this->institution($outsider, 'Other Desk');
        $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $otherSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '1',
            'idempotency_key' => 'cross-institution-rfq',
        ])->assertNotFound();
        $this->assertNotSame($clientInstitution->id, $otherInstitution->id);
    }

    public function test_otc_enforces_jurisdiction_account_type_compliance_and_manual_review_hooks(): void
    {
        $client = User::factory()->create();
        $admin = $this->admin('otc-policy-admin@example.com');
        [$institution, $subaccount] = $this->institution($client, 'Policy Desk');

        $this->actingAs($admin)->postJson('/api/admin/v1/otc/markets', [
            'symbol' => 'ETHUSDT',
            'base_asset' => 'ETH',
            'quote_asset' => 'USDT',
            'enabled' => true,
            'minimum_size' => '1',
            'maximum_size' => '100',
            'manual_review_threshold' => '10',
            'allowed_jurisdictions' => ['US'],
            'reason' => 'Jurisdiction gate.',
        ])->assertCreated();

        $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $subaccount->id,
            'symbol' => 'ETHUSDT',
            'side' => 'BUY',
            'base_amount' => '2',
            'idempotency_key' => 'jurisdiction-block',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/admin/v1/otc/markets', [
            'symbol' => 'ETHUSDT',
            'base_asset' => 'ETH',
            'quote_asset' => 'USDT',
            'enabled' => true,
            'minimum_size' => '1',
            'maximum_size' => '100',
            'manual_review_threshold' => '10',
            'allowed_jurisdictions' => ['NG'],
            'reason' => 'Enable local jurisdiction.',
        ])->assertCreated();

        $rfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $subaccount->id,
            'symbol' => 'ETHUSDT',
            'side' => 'BUY',
            'base_amount' => '12',
            'idempotency_key' => 'manual-review-hook',
        ])->assertCreated()->json('data');

        $this->assertSame('QUOTING', $rfq['status']);
        $this->assertSame(1, OtcRiskEvent::query()->where('event_type', 'OTC_MANUAL_RISK_APPROVAL_REQUIRED')->count());

        $institution->forceFill(['compliance_status' => 'RESTRICTED'])->save();
        $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $subaccount->id,
            'symbol' => 'ETHUSDT',
            'side' => 'BUY',
            'base_amount' => '2',
            'idempotency_key' => 'compliance-block',
        ])->assertStatus(422);
    }

    public function test_otc_best_execution_quote_expiry_counterparty_limit_and_external_settlement_state(): void
    {
        $client = User::factory()->create();
        $admin = $this->admin('otc-routing-admin@example.com');
        [$clientInstitution, $clientSubaccount] = $this->institution($client, 'Routing Client');
        [$mmInstitution, $mmSubaccount] = $this->institution(User::factory()->create(), 'Routing MM');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $clientSubaccount, 'USDT', '1000000', 'Seed client.');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $mmSubaccount, 'BTC', '20', 'Seed maker.');

        $this->actingAs($admin)->postJson('/api/admin/v1/otc/markets', [
            'symbol' => 'BTCUSDT',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'enabled' => true,
            'minimum_size' => '0.1',
            'maximum_size' => '25',
            'quote_ttl_seconds' => 60,
            'reason' => 'Enable routing test.',
        ])->assertCreated();

        $mmProfile = MarketMakerProfile::query()->create([
            'profile_uuid' => (string) Str::uuid(),
            'institution_id' => $mmInstitution->id,
            'subaccount_id' => $mmSubaccount->id,
            'status' => 'ACTIVE',
            'provider_type' => 'INSTITUTIONAL_MARKET_MAKER',
            'rate_profile' => 'MARKET_MAKER_STANDARD',
            'safety_mode' => 'NORMAL',
            'approved_markets' => ['BTCUSDT'],
            'limits' => ['otc_enabled' => true],
        ]);
        $expensive = $this->provider($admin, 'EXAEARN_MARKET_MAKER', ['BTCUSDT'], $mmProfile->id, $mmInstitution->id, $mmSubaccount->id);
        $cheap = $this->provider($admin, 'EXAEARN_MARKET_MAKER', ['BTCUSDT'], $mmProfile->id, $mmInstitution->id, $mmSubaccount->id);
        $external = $this->provider($admin, 'EXTERNAL_VENUE', ['BTCUSDT']);
        OtcCounterpartyExposure::query()->create([
            'provider_id' => $external->id,
            'asset' => 'USDT',
            'settlement_limit' => '100',
            'unsettled_notional' => '100',
        ]);

        $rfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '1',
            'idempotency_key' => 'routing-rfq',
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$rfq['rfq_uuid']}/providers/{$external->provider_uuid}/quotes", [
            'price' => '49900',
            'available_base_amount' => '1',
            'ttl_seconds' => 30,
        ])->assertStatus(422);

        $highQuote = $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$rfq['rfq_uuid']}/providers/{$expensive->provider_uuid}/quotes", [
            'price' => '51000',
            'available_base_amount' => '1',
            'ttl_seconds' => 30,
        ])->assertCreated()->json('data');
        OtcQuote::query()->where('quote_uuid', $highQuote['quote_uuid'])->update(['valid_until' => now()->subSecond()]);

        $lowQuote = $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$rfq['rfq_uuid']}/providers/{$cheap->provider_uuid}/quotes", [
            'price' => '50000',
            'available_base_amount' => '1',
            'client_fee' => '5',
            'ttl_seconds' => 30,
        ])->assertCreated()->json('data');

        $trade = $this->actingAs($client)->postJson("/api/institutional/otc/rfqs/{$rfq['rfq_uuid']}/accept", [
            'idempotency_key' => 'routing-accept',
        ])->assertCreated()->json('data');

        $this->assertSame('SETTLED', $trade['status']);
        $this->assertSame($lowQuote['quote_uuid'], OtcQuote::query()->findOrFail($trade['quote_id'])->quote_uuid);

        $external->forceFill(['status' => 'ACTIVE'])->save();
        OtcCounterpartyExposure::query()->where('provider_id', $external->id)->delete();
        $externalRfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '0.5',
            'idempotency_key' => 'external-rfq',
        ])->assertCreated()->json('data');
        $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$externalRfq['rfq_uuid']}/providers/{$external->provider_uuid}/quotes", [
            'price' => '50000',
            'available_base_amount' => '0.5',
            'ttl_seconds' => 30,
        ])->assertCreated();
        $externalTrade = $this->actingAs($client)->postJson("/api/institutional/otc/rfqs/{$externalRfq['rfq_uuid']}/accept", [
            'idempotency_key' => 'external-accept',
        ])->assertCreated()->json('data');

        $this->assertSame('SETTLING', $externalTrade['status']);
        $this->assertSame('PENDING', OtcSettlement::query()->where('trade_id', $externalTrade['id'])->firstOrFail()->status);
    }

    public function test_otc_blocks_insufficient_balance_and_supports_treasury_principal_settlement(): void
    {
        $client = User::factory()->create();
        $admin = $this->admin('otc-treasury-admin@example.com');
        [$clientInstitution, $clientSubaccount] = $this->institution($client, 'Treasury Client');
        app(InstitutionalService::class)->adminCreditSubaccount($admin, $clientSubaccount, 'USDT', '1000', 'Small client balance.');

        $this->actingAs($admin)->postJson('/api/admin/v1/otc/markets', [
            'symbol' => 'BTCUSDT',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'enabled' => true,
            'minimum_size' => '0.1',
            'maximum_size' => '10',
            'quote_ttl_seconds' => 60,
            'reason' => 'Enable treasury test.',
        ])->assertCreated();
        $treasury = $this->provider($admin, 'EXAEARN_TREASURY', ['BTCUSDT']);

        $rfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '1',
            'idempotency_key' => 'insufficient-rfq',
        ])->assertCreated()->json('data');
        $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$rfq['rfq_uuid']}/providers/{$treasury->provider_uuid}/quotes", [
            'price' => '50000',
            'available_base_amount' => '1',
            'ttl_seconds' => 30,
        ])->assertCreated();
        $this->actingAs($client)->postJson("/api/institutional/otc/rfqs/{$rfq['rfq_uuid']}/accept", [
            'idempotency_key' => 'insufficient-accept',
        ])->assertStatus(422);

        app(InstitutionalService::class)->adminCreditSubaccount($admin, $clientSubaccount, 'USDT', '100000', 'Fund client for treasury OTC.');
        $treasuryBase = app(LedgerService::class)->getOrCreateAccount(null, 'otc_treasury_principal', 'BTC');
        $treasuryFunding = app(LedgerService::class)->getOrCreateAccount(null, 'otc_treasury_seed', 'BTC');
        app(LedgerService::class)->postDoubleEntry('OTC-TREASURY-SEED', 'Seed OTC treasury BTC.', [
            ['account_id' => $treasuryFunding->id, 'amount' => '-3', 'asset' => 'BTC'],
            ['account_id' => $treasuryBase->id, 'amount' => '3', 'asset' => 'BTC'],
        ], 'otc_treasury_seed', ['source_service' => 'test']);

        $treasuryRfq = $this->actingAs($client)->postJson('/api/institutional/otc/rfqs', [
            'subaccount_id' => $clientSubaccount->id,
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'base_amount' => '1',
            'idempotency_key' => 'treasury-rfq',
        ])->assertCreated()->json('data');
        $this->actingAs($admin)->postJson("/api/admin/v1/otc/rfqs/{$treasuryRfq['rfq_uuid']}/providers/{$treasury->provider_uuid}/quotes", [
            'price' => '50000',
            'available_base_amount' => '1',
            'ttl_seconds' => 30,
        ])->assertCreated();
        $trade = $this->actingAs($client)->postJson("/api/institutional/otc/rfqs/{$treasuryRfq['rfq_uuid']}/accept", [
            'idempotency_key' => 'treasury-accept',
        ])->assertCreated()->json('data');

        $this->assertSame('SETTLED', $trade['status']);
        $this->assertSame('SETTLED', OtcSettlement::query()->where('trade_id', $trade['id'])->firstOrFail()->status);
        $this->assertSame('2.000000000000000000', (string) $treasuryBase->fresh()->balance);
        $this->assertSame($clientInstitution->id, OtcRfq::query()->where('rfq_uuid', $treasuryRfq['rfq_uuid'])->value('institution_id'));
    }

    private function institution(User $owner, string $deskName): array
    {
        $institution = InstitutionalAccount::query()->create([
            'institution_uuid' => (string) Str::uuid(),
            'master_user_id' => $owner->id,
            'legal_name' => $deskName.' Ltd',
            'country_of_incorporation' => 'NG',
            'business_type' => 'INSTITUTION',
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
        $subaccount = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'name' => $deskName,
            'type' => 'MARKET_MAKER',
            'status' => 'ACTIVE',
            'risk_mode' => 'ISOLATED',
            'product_flags' => ['OTC' => true, 'SPOT' => true],
        ]);

        return [$institution, $subaccount];
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'OTC Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }

    private function provider(Admin $admin, string $type, array $markets, ?int $marketMakerId = null, ?int $institutionId = null, ?int $subaccountId = null): OtcLiquidityProvider
    {
        $payload = $this->actingAs($admin)->postJson('/api/admin/v1/otc/providers', [
            'provider_type' => $type,
            'market_maker_id' => $marketMakerId,
            'institution_id' => $institutionId,
            'subaccount_id' => $subaccountId,
            'markets' => $markets,
            'reason' => "Enable {$type} for OTC.",
        ])->assertCreated()->json('data');

        return OtcLiquidityProvider::query()->where('provider_uuid', $payload['provider_uuid'])->firstOrFail();
    }
}
