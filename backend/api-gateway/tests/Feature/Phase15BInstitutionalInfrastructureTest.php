<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperRealtimeEvent;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalAuditEvent;
use App\Models\InstitutionalFeeProfile;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalRole;
use App\Models\InstitutionalSubaccount;
use App\Models\InstitutionalTransferRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\VipTierDefinition;
use App\Services\DeveloperApiKeyService;
use App\Services\FeeCalculator;
use App\Services\InstitutionalService;
use App\Services\VipTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase15BInstitutionalInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security-ratelimit.enabled' => false]);
        config(['institutional.large_transfer_threshold.USDT' => '50000']);
        config(['fees.spot.maker_bps' => '10']);
        config(['fees.spot.taker_bps' => '20']);
        config(['fees.vip.VIP_3.spot.maker_bps' => '4']);
    }

    public function test_institutional_onboarding_subaccount_transfer_vip_fee_reporting_and_controls(): void
    {
        $owner = User::factory()->create();
        $trader = User::factory()->create();
        $maker = $this->admin('inst-maker@example.com');
        $checker = $this->admin('inst-checker@example.com');

        $application = $this->actingAs($owner)->postJson('/api/institutional/apply', $this->applicationPayload())
            ->assertCreated()
            ->assertJsonPath('data.state', 'APPLICATION_PENDING')
            ->json('data');

        foreach (['KYB_PENDING', 'KYB_REVIEW', 'COMPLIANCE_REVIEW', 'RISK_REVIEW', 'COMMERCIAL_REVIEW', 'APPROVED'] as $state) {
            $this->actingAs($maker)->postJson("/api/admin/v1/institutional/applications/{$application['application_uuid']}/transition", [
                'state' => $state,
                'reason' => "Move to {$state}.",
            ])->assertOk();
        }

        $this->actingAs($maker)->postJson("/api/admin/v1/institutional/applications/{$application['application_uuid']}/activate", [
            'reason' => 'Maker cannot activate own approval.',
        ])->assertStatus(422);

        $activate = $this->actingAs($checker)->postJson("/api/admin/v1/institutional/applications/{$application['application_uuid']}/activate", [
            'reason' => 'Checker activates approved institutional master account.',
        ])->assertCreated()->json('data');

        $institution = InstitutionalAccount::query()->where('institution_uuid', $activate['institution_uuid'])->firstOrFail();
        $this->assertSame('ACTIVE', $institution->status);
        $this->assertSame(2, $institution->subaccounts()->count());
        $this->assertDatabaseHas('institutional_memberships', ['institution_id' => $institution->id, 'user_id' => $owner->id, 'status' => 'ACTIVE']);

        $spotDesk = $this->actingAs($owner)->postJson('/api/institutional/subaccounts', [
            'name' => 'BTC Spot Desk',
            'type' => 'SPOT',
        ])->assertCreated()->json('data');

        $treasury = InstitutionalSubaccount::query()->where('institution_id', $institution->id)->where('type', 'TREASURY')->firstOrFail();
        $spot = InstitutionalSubaccount::query()->where('subaccount_uuid', $spotDesk['subaccount_uuid'])->firstOrFail();
        app(InstitutionalService::class)->adminCreditSubaccount($checker, $treasury, 'USDT', '100000', 'Seed institutional treasury for test.');

        $autoTransfer = $this->actingAs($owner)->postJson('/api/institutional/transfers', [
            'source_subaccount_id' => $treasury->id,
            'destination_subaccount_id' => $spot->id,
            'asset' => 'USDT',
            'amount' => '20000',
            'idempotency_key' => 'inst-transfer-20k',
            'reference_note' => 'Treasury allocation to BTC desk.',
        ])->assertCreated()->json('data');
        $this->assertSame('COMPLETED', $autoTransfer['status']);

        $duplicate = $this->actingAs($owner)->postJson('/api/institutional/transfers', [
            'source_subaccount_id' => $treasury->id,
            'destination_subaccount_id' => $spot->id,
            'asset' => 'USDT',
            'amount' => '20000',
            'idempotency_key' => 'inst-transfer-20k',
        ])->assertCreated()->json('data');
        $this->assertSame($autoTransfer['transfer_uuid'], $duplicate['transfer_uuid']);
        $this->assertSame(1, InstitutionalTransferRequest::query()->where('idempotency_key', 'inst-transfer-20k')->count());

        $treasuryProjection = app(InstitutionalService::class)->canonicalSubaccountLedgerAccount($treasury->id, 'USDT');
        $spotProjection = app(InstitutionalService::class)->canonicalSubaccountLedgerAccount($spot->id, 'USDT');
        $this->assertSame('80000.000000000000000000', (string) $treasuryProjection->fresh()->balance);
        $this->assertSame('20000.000000000000000000', (string) $spotProjection->fresh()->balance);

        $large = $this->actingAs($owner)->postJson('/api/institutional/transfers', [
            'source_subaccount_id' => $treasury->id,
            'destination_subaccount_id' => $spot->id,
            'asset' => 'USDT',
            'amount' => '60000',
            'idempotency_key' => 'inst-transfer-60k',
        ])->assertCreated()->json('data');
        $this->assertSame('PENDING_APPROVAL', $large['status']);

        $this->actingAs($owner)->postJson("/api/institutional/transfers/{$large['transfer_uuid']}/approve", [
            'reason' => 'Owner cannot approve own large transfer.',
        ])->assertStatus(422);

        $approverRole = InstitutionalRole::query()->create([
            'institution_id' => $institution->id,
            'name' => 'TREASURY_APPROVER',
            'role_type' => 'TREASURY_MANAGER',
            'permissions' => ['APPROVE_TRANSFER', 'INTERNAL_TRANSFER', 'VIEW_BALANCES'],
        ]);
        $approverMembership = InstitutionalMembership::query()->create([
            'membership_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'institution_id' => $institution->id,
            'user_id' => $trader->id,
            'role_id' => $approverRole->id,
            'status' => 'ACTIVE',
            'accepted_at' => now(),
        ]);
        app(InstitutionalService::class)->grantSubaccountPermission($owner, $approverMembership, $treasury, 'APPROVE_TRANSFER');

        $this->actingAs($trader)->postJson("/api/institutional/transfers/{$large['transfer_uuid']}/approve", [
            'reason' => 'Approved by separate treasury approver.',
        ])->assertOk()->assertJsonPath('data.status', 'COMPLETED');

        $stream = "institution.{$institution->id}";
        $this->assertGreaterThanOrEqual(2, DeveloperRealtimeEvent::query()->whereNull('project_id')->where('user_id', $owner->id)->where('stream', $stream)->count());
        $this->actingAs($owner)->getJson('/api/institutional/realtime/replay?stream='.urlencode($stream).'&after_sequence=0')
            ->assertOk()
            ->assertJsonPath('data.0.sequence', 1);

        $this->assertSame('20000.000000000000000000', (string) $treasuryProjection->fresh()->balance);
        $this->assertSame('80000.000000000000000000', (string) $spotProjection->fresh()->balance);
        $this->assertSame('100000.000000000000000000', \App\Services\FinancialDecimal::add((string) $treasuryProjection->fresh()->balance, (string) $spotProjection->fresh()->balance));

        $outsiderInstitution = InstitutionalAccount::query()->create([
            'institution_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'master_user_id' => User::factory()->create()->id,
            'legal_name' => 'Other Fund',
            'country_of_incorporation' => 'GB',
            'business_type' => 'FUND',
            'status' => 'ACTIVE',
        ]);
        $outsiderSubaccount = InstitutionalSubaccount::query()->create([
            'subaccount_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'institution_id' => $outsiderInstitution->id,
            'name' => 'Other Treasury',
            'type' => 'TREASURY',
            'status' => 'ACTIVE',
        ]);
        $this->actingAs($owner)->postJson('/api/institutional/transfers', [
            'source_subaccount_id' => $treasury->id,
            'destination_subaccount_id' => $outsiderSubaccount->id,
            'asset' => 'USDT',
            'amount' => '1',
            'idempotency_key' => 'cross-inst',
        ])->assertNotFound();

        $project = app(DeveloperApiKeyService::class)->createProject($owner->id, ['name' => 'Institution API', 'environment' => 'sandbox']);
        $credentials = app(DeveloperApiKeyService::class)->createKey($owner->id, $project, [
            'name' => 'BTC desk key',
            'permissions' => ['account.read'],
            'institution_id' => $institution->id,
            'subaccount_id' => $spot->id,
            'rate_profile' => 'INSTITUTIONAL',
        ]);
        $key = DeveloperApiKey::query()->firstOrFail();
        $this->assertSame($institution->id, $key->institution_id);
        $this->assertSame($spot->id, $key->subaccount_id);
        $this->assertSame('INSTITUTIONAL', $key->rate_profile);

        $query = 'subaccount_id='.$treasury->id;
        $this->withHeaders($this->signedHeaders($credentials['api_key'], $credentials['api_secret'], 'GET', '/api/developer/v1/wallet/balances', $query, '[]', 'inst-spoof'))
            ->getJson('/api/developer/v1/wallet/balances?'.$query)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'SUBACCOUNT_SCOPE_VIOLATION');

        VipTierDefinition::query()->create([
            'tier' => 'VIP_3',
            'min_30d_spot_volume' => '1000000',
            'min_30d_futures_volume' => '0',
            'min_average_balance' => '0',
            'benefits' => ['spot' => ['maker_bps' => '4']],
        ]);
        app(VipTierService::class)->updateTier($institution, ['spot_volume_30d' => '1200000'], $checker, null, null, 'Volume qualification.');
        $this->assertSame('VIP_3', $institution->fresh()->vip_tier);

        $institution->forceFill(['compliance_status' => 'RESTRICTED'])->save();
        app(VipTierService::class)->updateTier($institution->fresh(), ['spot_volume_30d' => '1200000'], $checker, 'VIP_5', null, 'Compliance cap.');
        $this->assertSame('STANDARD', $institution->fresh()->vip_tier);
        $institution->forceFill(['compliance_status' => 'APPROVED', 'vip_tier' => 'VIP_3'])->save();

        $profile = $this->actingAs($checker)->postJson('/api/admin/v1/institutional/fee-profiles', [
            'name' => 'Atlas negotiated spot',
            'rules' => ['SPOT' => ['BTC/USDT' => ['maker_bps' => '2', 'taker_bps' => '5']]],
            'reason' => 'Negotiated institutional pricing.',
        ])->assertCreated()->json('data');
        $institution->forceFill(['fee_profile_id' => $profile['id']])->save();
        $fee = app(FeeCalculator::class)->institutionalMarket($institution->fresh('feeProfile'), 'SPOT', 'BTC/USDT', '10000', 'USDT', 'maker');
        $this->assertSame('2', $fee['rate_bps']);
        $this->assertSame('institutional_fee_profile', $fee['fee_policy_snapshot']['precedence']);
        $this->assertSame($profile['id'], $fee['fee_policy_snapshot']['fee_profile_id']);

        $report = app(InstitutionalService::class)->report($institution->fresh(), $owner);
        $this->assertSame('100000.000000000000000000', $report->summary['totals_by_asset']['USDT']);
        $this->assertTrue($report->summary['internal_transfers_excluded_from_revenue']);

        $this->actingAs($checker)->postJson("/api/admin/v1/institutional/institutions/{$institution->id}/status", [
            'status' => 'RESTRICTED',
            'reason' => 'Risk review.',
        ])->assertOk()->assertJsonPath('data.status', 'RESTRICTED');

        $this->assertGreaterThanOrEqual(10, InstitutionalAuditEvent::query()->count());
        $this->assertSame(1, InstitutionalFeeProfile::query()->count());
    }

    private function applicationPayload(): array
    {
        return [
            'legal_company_name' => 'Atlas Capital Ltd',
            'trading_name' => 'Atlas Capital',
            'incorporation_country' => 'Nigeria',
            'registration_number' => 'RC-1500000',
            'business_type' => 'FUND',
            'website' => 'https://atlas-capital.example',
            'contact_person' => 'Ada Finance',
            'business_email' => 'ops@atlas-capital.example',
            'expected_monthly_spot_volume' => '1200000',
            'expected_monthly_futures_volume' => '500000',
            'expected_assets_under_custody' => '250000',
            'team_size' => 12,
            'intended_products' => ['SPOT', 'FUTURES', 'API'],
            'api_requirements' => ['hmac', 'websocket', 'subaccounts'],
            'market_making_interest' => true,
            'otc_interest' => true,
            'fiat_requirements' => ['NGN', 'USD'],
            'subaccount_requirements' => ['TREASURY', 'SPOT', 'FUTURES'],
        ];
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'Institutional Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }

    private function signedHeaders(string $apiKey, string $apiSecret, string $method, string $path, string $query = '', string $body = '', ?string $nonce = null): array
    {
        $timestamp = (string) time();
        $nonce ??= 'nonce-' . uniqid('', true);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $query . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);

        return [
            'EXA-API-KEY' => $apiKey,
            'EXA-API-TIMESTAMP' => $timestamp,
            'EXA-API-NONCE' => $nonce,
            'EXA-API-SIGNATURE' => hash_hmac('sha256', $canonical, $apiSecret),
        ];
    }
}
