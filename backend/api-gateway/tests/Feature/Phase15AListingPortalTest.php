<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase15AListingPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/final-approval", [
            'reason' => 'All integration tests passed.',
        ])->assertOk()->assertJsonPath('data.integration_status', 'READY_FOR_LISTING');

        $this->actingAs($maker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/schedule", [
            'trading_open_at' => now()->addHour()->toISOString(),
            'reason' => 'Maker cannot schedule as final approver.',
        ])->assertStatus(422);

        $scheduleResponse = $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/schedule", [
            'announcement_at' => now()->toISOString(),
            'deposit_open_at' => now()->addMinutes(15)->toISOString(),
            'trading_open_at' => now()->addHour()->toISOString(),
            'withdrawal_open_at' => now()->addHours(2)->toISOString(),
            'reason' => 'Schedule approved after tests.',
        ]);
        $scheduleResponse->assertCreated()->assertJsonPath('data.status', 'SCHEDULED');

        $launchResponse = $this->actingAs($checker)->postJson("/api/admin/v1/listing-center/applications/{$reference}/launch");
        $launchResponse->assertAccepted()
            ->assertJsonPath('data.integration_status', 'LIVE')
            ->assertJsonPath('data.asset_configuration.trading_enabled', true);

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
        $this->actingAs($checker)->postJson('/api/admin/v1/listing-center/launch-events/process-due')
            ->assertAccepted();

        $application = ListingApplication::query()->where('reference', $reference)->with(['assetConfiguration', 'networkConfigurations'])->firstOrFail();
        $this->assertSame('LIVE', $application->integration_status);
        $this->assertTrue((bool) $application->assetConfiguration->deposit_enabled);
        $this->assertTrue((bool) $application->assetConfiguration->withdrawal_enabled);
        $this->assertTrue((bool) $application->assetConfiguration->trading_enabled);
        $this->assertSame(2, BlockchainAsset::query()->where('asset', 'ORBT')->where('deposit_enabled', true)->where('withdrawal_enabled', true)->count());
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
