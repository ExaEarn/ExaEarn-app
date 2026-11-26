<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
use App\Models\ComplianceJurisdiction;
use App\Models\ListingApplication;
use App\Models\ListingAssetNetworkConfiguration;
use App\Models\ListingAuditLog;
use App\Models\ListingContractValidation;
use App\Models\ListingLaunchEvent;
use App\Models\ListingLiquidityRequirement;
use App\Models\ListingMarketConfiguration;
use App\Models\ListingTokenMigration;
use App\Models\ListingTestRun;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use App\Jobs\ExecuteListingLaunchEvent;
use App\Services\ListingLifecycleService;
use App\Notifications\DeveloperPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase15AListingPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'listing.require_approved_kyb_for_listing_submission' => false,
            'listing.integration_certification_execution_enabled' => true,
        ]);
        Queue::fake();
        Redis::shouldReceive('publish')->zeroOrMoreTimes();

        config(['security-ratelimit.enabled' => false]);
    }

    public function test_listing_application_review_integration_and_controlled_launch_lifecycle(): void
    {
        $user = User::factory()->create();
        $maker = $this->admin('listing-maker@example.com');
        $checker = $this->admin('listing-checker@example.com');

        BlockchainNetwork::query()->create([
            'network' => 'ETHEREUM',
            'family' => 'EVM',
            'chain_id' => 1,
            'native_asset' => 'ETH',
            'state' => 'HEALTHY',
            'deposit_enabled' => true,
            'withdrawal_enabled' => true,
            'required_confirmations' => 12,
            'finality_confirmations' => 64,
        ]);

        $organizationResponse = $this->actingAs($user)->postJson('/api/listing/organizations', [
            'legal_name' => 'Atlas Protocol Ltd',
            'project_name' => 'Atlas Protocol',
            'jurisdiction' => 'Nigeria',
            'website' => 'https://atlas.example',
            'business_email' => 'listing@atlas.example',
        ]);
        $organizationResponse->assertCreated();

        $draftResponse = $this->actingAs($user)->postJson('/api/listing/organizations/'.$organizationResponse->json('data.id').'/applications', $this->applicationPayload());
        $draftResponse->assertCreated();
        $reference = (string) $draftResponse->json('data.reference');

        $submitResponse = $this->actingAs($user)->postJson("/api/listing/applications/{$reference}/submit", [
            'authorized_declaration' => true,
        ]);
        $submitResponse->assertAccepted()->assertJsonPath('data.application_status', 'SUBMITTED');

        foreach (['COMPLIANCE', 'TECHNICAL', 'SECURITY', 'LIQUIDITY'] as $reviewType) {
            $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/reviews", [
                'review_type' => $reviewType,
                'status' => 'PASSED',
                'score' => 92,
                'notes' => "{$reviewType} passed for integration.",
            ])->assertCreated();
        }

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/recommend", [
            'reason' => 'Reviews passed. Recommend controlled technical integration.',
        ])->assertOk();

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/approve", [
            'reason' => 'Maker cannot self-approve.',
        ])->assertStatus(422);

        $approveResponse = $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/approve", [
            'reason' => 'Second approver authorizes integration only.',
        ]);
        $approveResponse->assertOk()
            ->assertJsonPath('data.application_status', 'APPROVED')
            ->assertJsonPath('data.integration_status', 'INTEGRATION');

        $this->assertSame(0, Market::query()->where('symbol', 'ATLAS/USDT')->count(), 'Application approval must not create a live market.');

        $assetResponse = $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/asset-configuration", [
            'name' => 'Atlas Token',
            'symbol' => 'ATLAS',
            'asset_type' => 'TOKEN',
            'network' => 'ETHEREUM',
            'token_standard' => 'ERC-20',
            'contract_address' => '0x0000000000000000000000000000000000000a71',
            'decimals' => 18,
            'explorer_url' => 'https://etherscan.io/token/0x0000000000000000000000000000000000000a71',
        ]);
        $assetResponse->assertCreated()
            ->assertJsonPath('data.deposit_enabled', false)
            ->assertJsonPath('data.withdrawal_enabled', false)
            ->assertJsonPath('data.trading_enabled', false);

        $this->assertDatabaseHas('blockchain_assets', [
            'asset' => 'ATLAS',
            'network' => 'ETHEREUM',
            'deposit_enabled' => false,
            'withdrawal_enabled' => false,
        ]);

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/markets", [
            'quote_asset' => 'USDT',
            'manual_price' => '2.00',
        ])->assertStatus(422);
        $this->assertSame(0, Market::query()->where('symbol', 'ATLAS/USDT')->count());

        $marketResponse = $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/markets", [
            'quote_asset' => 'USDT',
            'tick_size' => '0.0001',
            'quantity_step' => '0.01',
            'min_quantity' => '1',
            'min_notional' => '5',
            'required_base_liquidity' => '50000',
            'required_quote_liquidity' => '100000',
            'maximum_spread_bps' => '60',
            'minimum_depth' => '25000',
            'liquidity_status' => 'READY',
        ]);
        $marketResponse->assertCreated()->assertJsonPath('data.status', 'PRE_LAUNCH');

        $market = Market::query()->where('symbol', 'ATLAS/USDT')->firstOrFail();
        $this->assertSame('pre_launch', $market->status);
        $this->assertSame('PRE_LAUNCH', $market->trading_status);
        $this->assertSame('0.00000000', (string) $market->last_price);

        $testResponse = $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/tests");
        $testResponse->assertAccepted()->assertJsonPath('data.overall_status', 'PASS');
        $requiredResults = collect($testResponse->json('data.results'))->where('required', true);
        $this->assertNotEmpty($requiredResults);
        $this->assertTrue($requiredResults->every(fn (array $result): bool => $result['status'] === 'PASS' && $result['evidence'] !== []));
        $this->assertFalse(collect($testResponse->json('data.results'))->contains('test_name', 'canonical_ledger_path'));
        $this->assertSame(0, User::query()->where('email', 'like', 'listing-cert-%')->count(), 'Rollback-isolated certification users must not persist.');

        $market->forceFill(['tick_size' => '0.0002'])->save();
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/final-approval", [
            'reason' => 'A stale certification must not authorize this changed market configuration.',
        ])->assertStatus(422);
        $market->forceFill(['tick_size' => '0.0001'])->save();

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/final-approval", [
            'reason' => 'All integration tests passed.',
        ])->assertOk()->assertJsonPath('data.integration_status', 'READY_FOR_LISTING');

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/schedule", [
            'trading_open_at' => now()->toISOString(),
            'reason' => 'Maker cannot schedule as final approver.',
        ])->assertStatus(422);

        $scheduleResponse = $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/schedule", [
            'announcement_at' => now()->toISOString(),
            'deposit_open_at' => now()->addMinutes(15)->toISOString(),
            'trading_open_at' => now()->toISOString(),
            'withdrawal_open_at' => now()->addHours(2)->toISOString(),
            'reason' => 'Schedule approved after tests.',
        ]);
        $scheduleResponse->assertCreated()->assertJsonPath('data.status', 'SCHEDULED');

        $launchResponse = $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/launch");
        $launchResponse->assertAccepted()
            ->assertJsonPath('data.operation.operation_status', 'DISPATCHED');
        Queue::pushed(ExecuteListingLaunchEvent::class)->each(
            fn (ExecuteListingLaunchEvent $job) => $job->handle(app(ListingLifecycleService::class))
        );
        $this->assertSame('LIVE', ListingApplication::query()->where('reference', $reference)->value('integration_status'));

        $this->assertDatabaseHas('markets', [
            'symbol' => 'ATLAS/USDT',
            'status' => 'active',
            'trading_status' => 'TRADING',
        ]);
        $this->assertSame(1, BlockchainAsset::query()->where('asset', 'ATLAS')->count());
        $this->assertSame(1, ListingMarketConfiguration::query()->where('symbol', 'ATLAS/USDT')->count());
        $this->assertSame('PASS', ListingTestRun::query()->latest()->firstOrFail()->overall_status);
        $this->assertGreaterThanOrEqual(8, ListingAuditLog::query()->count());
    }

    public function test_listing_portal_blocks_cross_owner_messages_and_duplicate_contracts(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = $this->admin('listing-admin@example.com');

        BlockchainNetwork::query()->create([
            'network' => 'BASE',
            'family' => 'EVM',
            'chain_id' => 8453,
            'native_asset' => 'ETH',
            'state' => 'HEALTHY',
        ]);

        $organization = $this->actingAs($owner)->postJson('/api/listing/organizations', [
            'legal_name' => 'Nova Labs',
            'project_name' => 'Nova',
            'jurisdiction' => 'US',
        ])->assertCreated()->json('data');

        $reference = (string) $this->actingAs($owner)
            ->postJson('/api/listing/organizations/'.$organization['id'].'/applications', $this->applicationPayload('NOVA'))
            ->assertCreated()
            ->json('data.reference');

        $this->actingAs($otherUser)->postJson("/api/listing/applications/{$reference}/messages", [
            'body' => 'Trying to access another project.',
        ])->assertNotFound();

        $this->actingAs($owner)->postJson("/api/listing/applications/{$reference}/submit", [
            'authorized_declaration' => true,
        ])->assertAccepted();

        foreach (['COMPLIANCE', 'TECHNICAL', 'SECURITY', 'LIQUIDITY'] as $reviewType) {
            $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/reviews", [
                'review_type' => $reviewType,
                'status' => 'PASSED',
            ])->assertCreated();
        }
        $checker = $this->admin('listing-checker-two@example.com');
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/recommend", ['reason' => 'ok'])->assertOk();
        $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/approve", ['reason' => 'ok'])->assertOk();

        $payload = [
            'name' => 'Nova Token',
            'symbol' => 'NOVA',
            'asset_type' => 'TOKEN',
            'network' => 'BASE',
            'token_standard' => 'ERC-20',
            'contract_address' => '0x0000000000000000000000000000000000000b45',
            'decimals' => 18,
        ];

        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/asset-configuration", $payload)->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/asset-configuration", $payload)->assertStatus(422);
    }

    public function test_listing_submission_requires_semantic_material_fields(): void
    {
        $owner = User::factory()->create();
        BlockchainNetwork::query()->create([
            'network' => 'ETHEREUM',
            'family' => 'EVM',
            'chain_id' => 1,
            'native_asset' => 'ETH',
            'state' => 'HEALTHY',
        ]);

        $organization = $this->actingAs($owner)->postJson('/api/listing/organizations', [
            'legal_name' => 'Semantic Labs',
            'project_name' => 'Semantic Token',
            'jurisdiction' => 'US',
        ])->assertCreated()->json('data');

        $payload = $this->applicationPayload('BAD');
        $payload['asset_information']['symbol'] = 'bad symbol';
        $payload['blockchain_information']['contract_address'] = 'not-a-contract';
        $payload['technology']['repository'] = 'not-a-url';
        $payload['security']['audit_reports'] = [];
        $payload['listing_request']['requested_pairs'] = ['BAD'];

        $application = $this->actingAs($owner)
            ->postJson('/api/listing/organizations/'.$organization['id'].'/applications', $payload)
            ->assertCreated()
            ->json('data');

        $this->actingAs($owner)
            ->postJson('/api/listing/applications/'.$application['reference'].'/submit', ['authorized_declaration' => true])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Application semantic validation failed: The asset information.symbol field format is invalid.');

        $this->assertSame(0, \App\Models\ListingReview::query()->where('application_id', $application['id'])->count());
    }

    public function test_final_listing_automation_multi_network_scheduler_recovery_and_migration_safety(): void
    {
        $owner = User::factory()->create();
        $maker = $this->admin('listing-auto-maker@example.com');
        $checker = $this->admin('listing-auto-checker@example.com');
        foreach ([
            ['network' => 'ETHEREUM', 'family' => 'EVM', 'chain_id' => 1, 'native_asset' => 'ETH'],
            ['network' => 'BSC', 'family' => 'EVM', 'chain_id' => 56, 'native_asset' => 'BNB'],
        ] as $network) {
            BlockchainNetwork::query()->create(array_merge($network, [
                'state' => 'HEALTHY',
                'deposit_enabled' => true,
                'withdrawal_enabled' => true,
                'required_confirmations' => 12,
                'finality_confirmations' => 32,
            ]));
        }

        $organization = $this->actingAs($owner)->postJson('/api/listing/organizations', [
            'legal_name' => 'Orbit Markets Ltd',
            'project_name' => 'Orbit',
            'jurisdiction' => 'NG',
        ])->assertCreated()->json('data');
        $reference = (string) $this->actingAs($owner)
            ->postJson('/api/listing/organizations/'.$organization['id'].'/applications', $this->applicationPayload('ORBT'))
            ->assertCreated()
            ->json('data.reference');
        $this->actingAs($owner)->postJson("/api/listing/applications/{$reference}/submit", ['authorized_declaration' => true])->assertAccepted();
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/launch")->assertStatus(422);

        foreach (['COMPLIANCE', 'TECHNICAL', 'SECURITY', 'LIQUIDITY'] as $reviewType) {
            $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/reviews", [
                'review_type' => $reviewType,
                'status' => 'PASSED',
            ])->assertCreated();
        }
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/recommend", ['reason' => 'Ready for technical integration.'])->assertOk();
        $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/approve", ['reason' => 'Approved for integration.'])->assertOk();

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/asset-configuration", [
            'name' => 'Orbit Token',
            'symbol' => 'ORBT',
            'asset_type' => 'TOKEN',
            'network' => 'ETHEREUM',
            'token_standard' => 'ERC-20',
            'contract_address' => '0x0000000000000000000000000000000000000c01',
            'decimals' => 18,
            'upgradeable' => true,
            'pausable' => true,
        ])->assertCreated();
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/networks", [
            'network' => 'UNSUPPORTED',
            'token_standard' => 'ERC-20',
            'contract_address' => '0x0000000000000000000000000000000000000c02',
            'decimals' => 18,
        ])->assertStatus(422);
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/networks", [
            'network' => 'BSC',
            'token_standard' => 'BEP-20',
            'contract_address' => '0x0000000000000000000000000000000000000c02',
            'decimals' => 18,
            'mintable' => true,
        ])->assertCreated()->assertJsonPath('data.deposit_enabled', false)->assertJsonPath('data.withdrawal_enabled', false);

        $this->assertSame(2, ListingAssetNetworkConfiguration::query()->where('application_id', ListingApplication::query()->where('reference', $reference)->value('id'))->count());
        $this->assertSame(2, ListingContractValidation::query()->where('status', 'PASS')->count());
        $this->assertTrue(ListingContractValidation::query()->whereJsonContains('risk_flags', 'UPGRADEABLE_CONTRACT')->exists());
        $this->assertTrue(ListingContractValidation::query()->whereJsonContains('risk_flags', 'MINTABLE')->exists());

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/markets", [
            'quote_asset' => 'USDT',
            'tick_size' => '0.0001',
            'quantity_step' => '0.01',
            'min_quantity' => '1',
            'min_notional' => '10',
            'liquidity_status' => 'LIQUIDITY_PENDING',
        ])->assertCreated();
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/tests")
            ->assertAccepted()
            ->assertJsonPath('data.overall_status', 'FAIL');
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/final-approval", ['reason' => 'Should block until liquidity ready.'])->assertStatus(422);

        ListingLiquidityRequirement::query()->where('application_id', ListingApplication::query()->where('reference', $reference)->value('id'))->update(['liquidity_status' => 'READY']);
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/tests")
            ->assertAccepted()
            ->assertJsonPath('data.overall_status', 'PASS');
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/final-approval", ['reason' => 'All final checks pass.'])->assertOk();

        // Contract validation alone cannot authorize a financial deposit capability.
        $this->actingAs($maker)->getJson("/api/admin/v1/listing-center/applications/{$reference}/deposit-readiness")
            ->assertOk()
            ->assertJsonPath('data.overall_status', 'BLOCKED');
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/deposit-activation/request", [
            'reason' => 'Contract validation is not sufficient.',
        ])->assertStatus(422);

        config(['custody.production_enabled' => true]);
        $operationalEvidence = [
            'address_generation' => 'PASS', 'memo_tag_policy' => 'PASS', 'deposit_monitor' => 'PASS',
            'ledger_posting' => 'PASS', 'reconciliation' => 'PASS', 'risk_controls' => 'PASS',
            'security_controls' => 'PASS', 'operational_runbook' => 'PASS',
        ];
        ListingAssetNetworkConfiguration::query()->where('application_id', ListingApplication::query()->where('reference', $reference)->value('id'))
            ->get()->each(function (ListingAssetNetworkConfiguration $configuration) use ($operationalEvidence): void {
                $configuration->update(['metadata' => ['deposit_readiness' => $operationalEvidence]]);
                DB::table('custody_wallets')->insert([
                    'wallet_id' => 'listing-certification-'.$configuration->id,
                    'classification' => 'USER_DEPOSIT', 'network' => strtolower($configuration->network),
                    'asset' => 'ORBT', 'address' => '0x'.str_pad(dechex($configuration->id), 40, '0', STR_PAD_LEFT),
                    'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
                ]);
            });

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/deposit-activation/request", [
            'reason' => 'All canonical deposit readiness gates passed.',
        ])->assertAccepted()->assertJsonPath('data.status', 'REQUESTED');
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/deposit-activation/approve")
            ->assertStatus(422);
        $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/deposit-activation/approve")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');

        // A live market and development signer cannot authorize production withdrawals.
        $this->actingAs($maker)->getJson("/api/admin/v1/listing-center/applications/{$reference}/withdrawal-readiness")
            ->assertOk()
            ->assertJsonPath('data.overall_status', 'BLOCKED')
            ->assertJsonPath('data.networks.0.gates.SIGNER_READY.status', 'NOT_CONNECTED');
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/withdrawal-activation/request", [
            'reason' => 'Development signing must remain blocked.',
        ])->assertStatus(422);

        // Operational evidence is material configuration, so trading certification is rerun too.
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/tests")
            ->assertAccepted()->assertJsonPath('data.overall_status', 'PASS');
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/final-approval", [
            'reason' => 'Re-certified after deposit readiness configuration.',
        ])->assertOk();

        $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/schedule", [
            'announcement_at' => now()->subMinutes(20)->toISOString(),
            'deposit_open_at' => now()->subMinutes(15)->toISOString(),
            'trading_open_at' => now()->subMinutes(10)->toISOString(),
            'withdrawal_open_at' => now()->subMinutes(5)->toISOString(),
            'reason' => 'Due schedule recovery test.',
        ])->assertCreated();

        $this->assertSame(4, ListingLaunchEvent::query()->where('status', 'PENDING')->count());
        $this->actingAs($checker)->postJson('/api/admin/v1/listing-center/launch-events/process-due')
            ->assertAccepted();
        Queue::pushed(ExecuteListingLaunchEvent::class)->each(
            fn (ExecuteListingLaunchEvent $job) => $job->handle(app(ListingLifecycleService::class))
        );
        $this->actingAs($checker)->postJson('/api/admin/v1/listing-center/launch-events/process-due')
            ->assertAccepted();

        $application = ListingApplication::query()->where('reference', $reference)->with(['assetConfiguration', 'networkConfigurations'])->firstOrFail();
        $this->assertSame('LIVE', $application->integration_status);
        $this->assertTrue((bool) $application->assetConfiguration->deposit_enabled);
        $this->assertFalse((bool) $application->assetConfiguration->withdrawal_enabled);
        $this->assertTrue((bool) $application->assetConfiguration->trading_enabled);
        $this->assertSame(0, BlockchainAsset::query()->where('asset', 'ORBT')->where('withdrawal_enabled', true)->count());
        $this->assertSame('BLOCKED', ListingLaunchEvent::query()->where('event_type', 'WITHDRAWAL_OPEN')->value('status'));
        $this->assertSame(0, ListingLaunchEvent::query()->where('status', 'PENDING')->count());

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/token-migrations", [
            'migration_type' => 'CONTRACT_MIGRATION',
            'old_network' => 'ETHEREUM',
            'old_contract_address' => '0x0000000000000000000000000000000000000c01',
            'new_network' => 'ETHEREUM',
            'new_contract_address' => '0x0000000000000000000000000000000000000c01',
            'reason' => 'Invalid same contract migration.',
        ])->assertStatus(422);
        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/token-migrations", [
            'migration_type' => 'CONTRACT_MIGRATION',
            'old_network' => 'ETHEREUM',
            'old_contract_address' => '0x0000000000000000000000000000000000000c01',
            'new_network' => 'ETHEREUM',
            'new_contract_address' => '0x0000000000000000000000000000000000000c03',
            'reason' => 'Project is migrating to an audited contract.',
            'plan' => ['effective_at' => now()->addWeek()->toISOString(), 'user_balance_impact' => 'none_before_swap'],
        ])->assertAccepted();
        $this->assertSame(1, ListingTokenMigration::query()->where('status', 'PENDING_APPROVAL')->count());
    }

    public function test_listing_draft_autosave_member_roles_and_private_document_authorization(): void
    {
        Storage::fake('local');
        Storage::fake('listing_quarantine');
        $owner = User::factory()->create();
        $contributor = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();

        $organization = $this->actingAs($owner)->postJson('/api/listing/organizations', [
            'legal_name' => 'Governed Asset Labs',
            'project_name' => 'Governed Asset',
            'jurisdiction' => 'NG',
        ])->assertCreated()->json('data');

        $this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/team", [
            'email' => $contributor->email,
            'role' => 'CONTRIBUTOR',
        ])->assertCreated();
        $this->actingAs($owner)->postJson("/api/listing/organizations/{$organization['id']}/team", [
            'email' => $viewer->email,
            'role' => 'VIEWER',
        ])->assertCreated();

        \App\Models\ListingTeamMember::query()->where('email', $contributor->email)->update(['user_id' => $contributor->id]);
        \App\Models\ListingTeamMember::query()->where('email', $viewer->email)->update(['user_id' => $viewer->id]);

        $payload = $this->applicationPayload('GOV');
        $reference = (string) $this->actingAs($owner)
            ->postJson("/api/listing/organizations/{$organization['id']}/applications", $payload)
            ->assertCreated()->json('data.reference');

        $payload['project_information']['summary'] = 'Updated through idempotent autosave.';
        $this->actingAs($contributor)
            ->postJson("/api/listing/organizations/{$organization['id']}/applications", $payload)
            ->assertOk()->assertJsonPath('data.project_information.summary', 'Updated through idempotent autosave.');
        $this->actingAs($viewer)
            ->postJson("/api/listing/organizations/{$organization['id']}/applications", $payload)
            ->assertStatus(422);

        $document = $this->actingAs($contributor)->post("/api/listing/applications/{$reference}/documents", [
            'document_type' => 'WHITEPAPER',
            'file' => UploadedFile::fake()->create('whitepaper.pdf', 200, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.visibility', 'PRIVATE')->assertJsonPath('data.version', 1)->json('data');

        $this->assertArrayNotHasKey('storage_path', $document);
        $this->assertSame(64, strlen((string) $document['checksum_sha256']));
        $this->actingAs($outsider)->get("/api/listing/applications/{$reference}/documents/{$document['document_uuid']}")->assertNotFound();
        $this->actingAs($viewer)->get("/api/listing/applications/{$reference}/documents/{$document['document_uuid']}")->assertUnprocessable();
        $reviewer = $this->admin('listing-malware-reviewer@example.com');
        $this->actingAs($reviewer)->postJson("/api/admin/v1/listing-center/applications/{$reference}/approve", ['reason' => 'Must not use quarantined evidence.'])
            ->assertUnprocessable()->assertJsonFragment(['message' => 'Listing approval blocked: current WHITEPAPER document is not malware-cleared.']);
        \App\Models\ListingDocument::query()->where('document_uuid', $document['document_uuid'])->update(['scan_status' => 'INFECTED']);
        $this->actingAs($viewer)->get("/api/listing/applications/{$reference}/documents/{$document['document_uuid']}")->assertUnprocessable();
    }

    public function test_information_request_and_rejection_are_explicit_audited_terminal_controls(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin('listing-review-control@example.com');
        $organization = $this->actingAs($owner)->postJson('/api/listing/organizations', [
            'legal_name' => 'Review Controls Ltd', 'project_name' => 'Review Controls', 'jurisdiction' => 'NG',
        ])->assertCreated()->json('data');
        $reference = (string) $this->actingAs($owner)
            ->postJson("/api/listing/organizations/{$organization['id']}/applications", $this->applicationPayload('CTRL'))
            ->assertCreated()->json('data.reference');
        $this->actingAs($owner)->postJson("/api/listing/applications/{$reference}/submit", ['authorized_declaration' => true])->assertAccepted();

        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/information-requests", [
            'subject' => 'Updated legal opinion',
            'body' => 'Please provide an updated jurisdiction-specific legal opinion.',
        ])->assertCreated()->assertJsonPath('data.message_type', 'INFORMATION_REQUEST');
        $this->assertDatabaseHas('listing_applications', ['reference' => $reference, 'application_status' => 'INFORMATION_REQUESTED']);

        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/reject", [
            'reason' => 'Required legal evidence was not provided after formal request.',
        ])->assertOk()->assertJsonPath('data.application_status', 'REJECTED');
        $this->actingAs($admin)->postJson("/api/admin/v1/listing-center/applications/{$reference}/reject", [
            'reason' => 'Attempting to repeat a terminal decision is prohibited.',
        ])->assertStatus(422);
        $this->assertDatabaseHas('listing_audit_logs', ['action' => 'listing.application.rejected']);
    }

    public function test_listing_signup_uses_canonical_user_identity_and_shared_session(): void
    {
        ComplianceJurisdiction::query()->create([
            'country_code' => 'NG',
            'country_name' => 'Nigeria',
            'status' => 'ALLOWED',
            'risk_level' => 'STANDARD',
            'policy_version' => 'listing-test-v1',
        ]);

        $this->getJson('/api/registration/metadata')
            ->assertOk()
            ->assertJsonPath('data.countries.0.code', 'NG')
            ->assertJsonPath('data.countries.0.registration_available', true);

        $response = $this->postJson('/api/register', [
            'name' => 'Listing Applicant',
            'email' => 'canonical-listing@example.com',
            'country' => 'NG',
            'password' => 'Secure!Pass123',
            'password_confirmation' => 'Secure!Pass123',
            'terms_accepted' => true,
            'listing_terms_accepted' => true,
            'registration_context' => 'listing',
        ]);
        $response->assertCreated()
            ->assertJsonPath('next', 'listing_email_verification')
            ->assertJsonPath('user.email', 'canonical-listing@example.com');

        $userId = (int) $response->json('user.id');
        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'canonical-listing@example.com', 'residence_country' => 'NG']);
        $this->assertDatabaseMissing('listing_team_members', ['user_id' => $userId]);
        $this->assertDatabaseHas('user_legal_consents', [
            'user_id' => $userId,
            'consent_type' => 'EXAEARN_TERMS_AND_PRIVACY',
            'document_version' => config('listing.account_terms_version'),
        ]);
        $this->assertDatabaseHas('user_legal_consents', [
            'user_id' => $userId,
            'consent_type' => 'LISTING_PORTAL_TERMS',
            'document_version' => config('listing.portal_terms_version'),
        ]);

        $this->postJson('/api/listing/organizations', [
            'legal_name' => 'Canonical Identity Labs',
            'project_name' => 'Canonical Identity',
            'jurisdiction' => 'NG',
        ])->assertCreated()->assertJsonPath('data.owner_user_id', $userId);
    }

    public function test_listing_signup_fails_closed_for_blocked_jurisdiction(): void
    {
        ComplianceJurisdiction::query()->create([
            'country_code' => 'ZZ',
            'country_name' => 'Restricted Test Region',
            'status' => 'BLOCKED',
            'risk_level' => 'PROHIBITED',
            'policy_version' => 'listing-test-v1',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Restricted Applicant',
            'email' => 'restricted-listing@example.com',
            'country' => 'ZZ',
            'password' => 'Secure!Pass123',
            'password_confirmation' => 'Secure!Pass123',
            'terms_accepted' => true,
            'listing_terms_accepted' => true,
            'registration_context' => 'listing',
        ])->assertUnprocessable()->assertJsonPath('code', 'JURISDICTION_RESTRICTED');

        $this->assertDatabaseMissing('users', ['email' => 'restricted-listing@example.com']);
    }

    public function test_listing_password_reset_notification_returns_to_listing_portal(): void
    {
        config(['app.listing_portal_url' => 'https://listing.exaearn.test']);
        $user = User::factory()->create(['email' => 'listing-recovery@example.com']);

        $message = (new DeveloperPasswordResetNotification('listing-reset-token', 'listing'))->toMail($user);

        $this->assertSame(
            'https://listing.exaearn.test/reset?token=listing-reset-token&email=listing-recovery%40example.com',
            $message->actionUrl
        );
    }

    private function applicationPayload(string $symbol = 'ATLAS'): array
    {
        return [
            'idempotency_key' => 'listing-'.$symbol,
            'application_type' => 'NEW_TOKEN_LISTING',
            'project_information' => ['name' => "{$symbol} Protocol", 'summary' => 'Settlement infrastructure token.'],
            'asset_information' => ['name' => "{$symbol} Token", 'symbol' => $symbol],
            'blockchain_information' => ['network' => 'ETHEREUM', 'contract_address' => '0x0000000000000000000000000000000000000a71', 'token_standard' => 'ERC-20'],
            'tokenomics' => ['total_supply' => '1000000000'],
            'technology' => ['repository' => 'https://github.com/example/token', 'audit_reports' => ['https://audit.example/report.pdf']],
            'security' => ['audit_reports' => ['https://audit.example/report.pdf'], 'known_incidents' => 'none'],
            'legal_compliance' => ['legal_opinion' => 'provided', 'restricted_jurisdictions' => []],
            'market_community' => ['website' => 'https://project.example', 'community_channels' => ['x', 'telegram']],
            'liquidity' => ['market_maker' => 'institutional', 'expected_depth' => 'ready'],
            'listing_request' => ['requested_pairs' => ["{$symbol}/USDT"], 'requested_launch_window' => 'standard'],
        ];
    }

    private function admin(string $email): Admin
    {
        $role = Role::query()->firstOrCreate(['name' => 'super_admin']);

        return Admin::query()->create([
            'name' => 'Listing Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);
    }
}
