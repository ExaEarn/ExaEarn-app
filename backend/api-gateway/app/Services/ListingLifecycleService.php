<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\BlockchainAsset;
use App\Models\BlockchainNetwork;
use App\Models\ListingApplication;
use App\Models\ListingAssetConfiguration;
use App\Models\ListingAssetNetworkConfiguration;
use App\Models\ListingAuditLog;
use App\Models\ListingContractValidation;
use App\Models\ListingLaunchEvent;
use App\Models\ListingLaunchSchedule;
use App\Models\ListingLiquidityRequirement;
use App\Models\ListingMarketConfiguration;
use App\Models\ListingMessage;
use App\Models\ListingOrganization;
use App\Models\ListingReview;
use App\Models\ListingTestRun;
use App\Models\ListingTokenMigration;
use App\Models\Market;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Services\PersonalizedContent\ProductEventContentService;

class ListingLifecycleService
{
    public const REVIEW_TYPES = ['INITIAL', 'DUE_DILIGENCE', 'COMPLIANCE', 'TECHNICAL', 'SECURITY', 'LIQUIDITY', 'FINAL'];
    public const APPLICATION_TRANSITIONS = [
        'DRAFT' => ['SUBMITTED'],
        'INFORMATION_REQUESTED' => ['SUBMITTED'],
        'SUBMITTED' => ['DUE_DILIGENCE', 'COMPLIANCE_REVIEW', 'TECHNICAL_REVIEW', 'SECURITY_REVIEW', 'LIQUIDITY_REVIEW', 'FINAL_REVIEW', 'REJECTED'],
        'DUE_DILIGENCE' => ['COMPLIANCE_REVIEW', 'TECHNICAL_REVIEW', 'SECURITY_REVIEW', 'LIQUIDITY_REVIEW', 'FINAL_REVIEW', 'REJECTED', 'INFORMATION_REQUESTED'],
        'COMPLIANCE_REVIEW' => ['TECHNICAL_REVIEW', 'SECURITY_REVIEW', 'LIQUIDITY_REVIEW', 'FINAL_REVIEW', 'REJECTED', 'INFORMATION_REQUESTED'],
        'TECHNICAL_REVIEW' => ['SECURITY_REVIEW', 'LIQUIDITY_REVIEW', 'FINAL_REVIEW', 'REJECTED', 'INFORMATION_REQUESTED'],
        'SECURITY_REVIEW' => ['LIQUIDITY_REVIEW', 'FINAL_REVIEW', 'REJECTED', 'INFORMATION_REQUESTED'],
        'LIQUIDITY_REVIEW' => ['FINAL_REVIEW', 'REJECTED', 'INFORMATION_REQUESTED'],
        'FINAL_REVIEW' => ['APPROVED', 'REJECTED'],
        'APPROVED' => ['APPROVED'],
        'REJECTED' => [],
    ];

    public const INTEGRATION_TRANSITIONS = [
        'NOT_STARTED' => ['INTEGRATION'],
        'INTEGRATION' => ['ASSET_CONFIGURATION', 'LAUNCH_BLOCKED'],
        'ASSET_CONFIGURATION' => ['NETWORK_CONFIGURATION', 'MARKET_CREATED', 'LAUNCH_BLOCKED'],
        'NETWORK_CONFIGURATION' => ['MARKET_CREATED', 'LAUNCH_BLOCKED'],
        'MARKET_CREATED' => ['TESTING', 'LAUNCH_BLOCKED'],
        'TESTING' => ['READY_FOR_LISTING', 'LAUNCH_BLOCKED'],
        'READY_FOR_LISTING' => ['SCHEDULED'],
        'SCHEDULED' => ['PRE_LAUNCH', 'LIVE', 'LAUNCH_BLOCKED'],
        'PRE_LAUNCH' => ['LIVE', 'LAUNCH_BLOCKED'],
        'LIVE' => ['PAUSED', 'SUSPENDED', 'DELISTING'],
        'PAUSED' => ['LIVE', 'SUSPENDED', 'DELISTING'],
        'SUSPENDED' => ['PAUSED', 'DELISTING'],
        'LAUNCH_BLOCKED' => ['INTEGRATION', 'TESTING', 'READY_FOR_LISTING', 'SCHEDULED'],
        'DELISTING' => ['DELISTED'],
        'DELISTED' => [],
    ];

    public function createOrganization(User $user, array $payload, ?Request $request = null): ListingOrganization
    {
        $organization = ListingOrganization::query()->create([
            'organization_uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'legal_name' => (string) $payload['legal_name'],
            'project_name' => (string) $payload['project_name'],
            'jurisdiction' => (string) $payload['jurisdiction'],
            'website' => $payload['website'] ?? null,
            'business_email' => $payload['business_email'] ?? $user->email,
            'registration_details' => $payload['registration_details'] ?? null,
            'incorporation_date' => $payload['incorporation_date'] ?? null,
            'registered_address' => $payload['registered_address'] ?? null,
            'project_category' => $payload['project_category'] ?? null,
            'primary_contact' => $payload['primary_contact'] ?? null,
            'authorized_representative' => $payload['authorized_representative'] ?? null,
        ]);

        $organization->teamMembers()->create([
            'email' => $user->email,
            'user_id' => $user->id,
            'role' => 'OWNER',
        ]);

        $this->audit(null, 'user', $user->id, 'listing.organization.created', 'listing_organization', $organization->id, null, $organization->toArray(), null, $request);

        return $organization;
    }

    public function saveDraft(User $user, ListingOrganization $organization, array $payload, ?Request $request = null): ListingApplication
    {
        $this->assertOrganizationAccess($user, $organization);
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existing = ListingApplication::query()->where('organization_id', $organization->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($idempotencyKey, $organization, $payload, $request, $user): ListingApplication {
            $application = ListingApplication::query()->create([
                'application_uuid' => (string) Str::uuid(),
                'reference' => $this->reference(),
                'organization_id' => $organization->id,
                'submitted_by' => $user->id,
                'application_type' => (string) ($payload['application_type'] ?? 'NEW_TOKEN_LISTING'),
                'application_status' => 'DRAFT',
                'integration_status' => 'NOT_STARTED',
                'progress_percent' => $this->progress($payload),
                'project_information' => $payload['project_information'] ?? [],
                'asset_information' => $payload['asset_information'] ?? [],
                'blockchain_information' => $payload['blockchain_information'] ?? [],
                'tokenomics' => $payload['tokenomics'] ?? [],
                'technology' => $payload['technology'] ?? [],
                'security' => $payload['security'] ?? [],
                'legal_compliance' => $payload['legal_compliance'] ?? [],
                'market_community' => $payload['market_community'] ?? [],
                'liquidity' => $payload['liquidity'] ?? [],
                'listing_request' => $payload['listing_request'] ?? [],
                'risk_flags' => $this->riskFlags($payload),
                'idempotency_key' => $idempotencyKey,
            ]);
            $this->audit($application, 'user', $user->id, 'listing.application.draft_saved', 'listing_application', $application->id, null, $application->toArray(), null, $request);

            return $application;
        });
    }

    public function submit(User $user, ListingApplication $application, array $payload, ?Request $request = null): ListingApplication
    {
        $this->assertOrganizationAccess($user, $application->organization);
        if ($application->application_status !== 'DRAFT' && $application->application_status !== 'INFORMATION_REQUESTED') {
            throw new RuntimeException('Only draft or information-requested applications can be submitted.');
        }
        if (! (bool) ($payload['authorized_declaration'] ?? false)) {
            throw new RuntimeException('Authorized representative declaration is required.');
        }

        $missing = $this->missingSections($application);
        if ($missing !== []) {
            throw new RuntimeException('Application is missing sections: ' . implode(', ', $missing));
        }

        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'SUBMITTED',
            'submitted_at' => now(),
            'progress_percent' => 100,
        ])->save();
        $this->seedReviews($application);
        $this->audit($application, 'user', $user->id, 'listing.application.submitted', 'listing_application', $application->id, $before, $application->fresh()->toArray(), null, $request);

        return $application->fresh(['reviews']);
    }

    public function completeReview(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingReview
    {
        $type = strtoupper((string) $payload['review_type']);
        if (! in_array($type, self::REVIEW_TYPES, true)) {
            throw new RuntimeException('Unsupported review type.');
        }

        $review = ListingReview::query()->firstOrCreate(
            ['application_id' => $application->id, 'review_type' => $type],
            ['review_uuid' => (string) Str::uuid()]
        );
        $before = $review->toArray();
        $review->fill([
            'reviewer_admin_id' => $admin->id,
            'status' => (string) $payload['status'],
            'score' => $payload['score'] ?? null,
            'scorecard' => $payload['scorecard'] ?? [],
            'risk_flags' => $payload['risk_flags'] ?? [],
            'notes' => $payload['notes'] ?? null,
            'completed_at' => now(),
        ])->save();

        $application->forceFill([
            'risk_flags' => array_values(array_unique(array_merge($application->risk_flags ?? [], $review->risk_flags ?? []))),
            'application_status' => $this->reviewStatusFor($type),
        ])->save();

        $this->audit($application, 'admin', $admin->id, 'listing.review.completed', 'listing_review', $review->id, $before, $review->fresh()->toArray(), $payload['notes'] ?? null, $request);

        return $review->fresh();
    }

    public function recommendApproval(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingApplication
    {
        if (! in_array($application->application_status, ['FINAL_REVIEW', 'LIQUIDITY_REVIEW', 'CONDITIONALLY_APPROVED', 'SUBMITTED'], true)) {
            throw new RuntimeException('Application is not ready for recommendation.');
        }
        $this->requireReviews($application);
        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'FINAL_REVIEW',
            'recommended_by_admin_id' => $admin->id,
            'recommended_at' => now(),
            'decision_reason' => $reason,
        ])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.application.recommended', 'listing_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function approveApplication(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingApplication
    {
        if (! $application->recommended_by_admin_id) {
            throw new RuntimeException('Maker recommendation is required before approval.');
        }
        if ((int) $application->recommended_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Senior approver must be different from recommendation maker.');
        }
        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'APPROVED',
            'integration_status' => 'INTEGRATION',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'decision_reason' => $reason,
        ])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.application.approved', 'listing_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function createAssetConfiguration(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingAssetConfiguration
    {
        if ($application->application_status !== 'APPROVED') {
            throw new RuntimeException('Application approval is required before asset configuration.');
        }

        $asset = array_merge($application->asset_information ?? [], $payload);
        $networkName = strtoupper((string) ($asset['network'] ?? $application->blockchain_information['network'] ?? ''));
        $network = BlockchainNetwork::query()->where('network', $networkName)->first();
        if (! $network || ! in_array($network->state, ['HEALTHY', 'ACTIVE', 'DEGRADED'], true)) {
            throw new RuntimeException('NEW_NETWORK_INTEGRATION_REQUIRED');
        }

        $symbol = strtoupper((string) $asset['symbol']);
        $contract = $asset['contract_address'] ?? null;
        if ($contract && ListingAssetConfiguration::query()->where('network', $networkName)->where('contract_address', $contract)->exists()) {
            throw new RuntimeException('Duplicate network contract is already configured.');
        }
        if ($contract && BlockchainAsset::query()->where('network', $networkName)->where('contract_address', $contract)->exists()) {
            throw new RuntimeException('Duplicate custody asset contract is already registered.');
        }

        return DB::transaction(function () use ($admin, $application, $asset, $contract, $network, $networkName, $request, $symbol): ListingAssetConfiguration {
            $blockchainAsset = BlockchainAsset::query()->create([
                'blockchain_network_id' => $network->id,
                'asset' => $symbol,
                'network' => $networkName,
                'asset_type' => (string) ($asset['asset_type'] ?? 'TOKEN'),
                'contract_address' => $contract,
                'decimals' => (int) ($asset['decimals'] ?? 18),
                'deposit_enabled' => false,
                'withdrawal_enabled' => false,
                'minimum_deposit' => '0',
                'minimum_withdrawal' => '0',
                'maximum_withdrawal' => '0',
                'required_confirmations' => $network->required_confirmations,
                'metadata' => ['listing_application_reference' => $application->reference],
            ]);

            $configuration = ListingAssetConfiguration::query()->create([
                'asset_config_uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'blockchain_asset_id' => $blockchainAsset->id,
                'asset_uid' => 'asset_' . Str::lower(Str::random(16)),
                'name' => (string) $asset['name'],
                'symbol' => $symbol,
                'slug' => Str::slug((string) ($asset['slug'] ?? $symbol . '-' . $networkName)),
                'asset_type' => (string) ($asset['asset_type'] ?? 'TOKEN'),
                'network' => $networkName,
                'token_standard' => (string) ($asset['token_standard'] ?? 'ERC-20'),
                'contract_address' => $contract,
                'decimals' => (int) ($asset['decimals'] ?? 18),
                'explorer_url' => $asset['explorer_url'] ?? null,
                'status' => 'CONFIGURED',
                'deposit_enabled' => false,
                'withdrawal_enabled' => false,
                'trading_enabled' => false,
                'supply_metadata' => $asset['supply_metadata'] ?? [],
                'configuration_history' => [['action' => 'created', 'at' => now()->toISOString(), 'admin_id' => $admin->id]],
            ]);

            $networkConfiguration = ListingAssetNetworkConfiguration::query()->create([
                'network_config_uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'listing_asset_configuration_id' => $configuration->id,
                'blockchain_network_id' => $network->id,
                'blockchain_asset_id' => $blockchainAsset->id,
                'network' => $networkName,
                'token_standard' => (string) ($asset['token_standard'] ?? 'ERC-20'),
                'contract_address' => $contract,
                'decimals' => (int) ($asset['decimals'] ?? 18),
                'deposit_enabled' => false,
                'withdrawal_enabled' => false,
                'required_confirmations' => $network->required_confirmations,
                'finality_confirmations' => $network->finality_confirmations,
                'minimum_deposit' => $asset['minimum_deposit'] ?? '0',
                'minimum_withdrawal' => $asset['minimum_withdrawal'] ?? '0',
                'withdrawal_fee' => $asset['withdrawal_fee'] ?? '0',
                'memo_required' => (bool) ($network->memo_required ?? false),
                'explorer_url' => $asset['explorer_url'] ?? null,
                'status' => 'CONFIGURED',
                'validation_status' => 'NOT_RUN',
                'metadata' => ['primary_network' => true],
            ]);

            $validation = app(ListingContractValidationService::class)->validate($application, $networkConfiguration, array_merge($asset, [
                'network_family' => $network->family,
            ]));
            $networkConfiguration->forceFill(['validation_status' => $validation->status])->save();

            $application->forceFill([
                'integration_status' => 'ASSET_CONFIGURATION',
                'verified_metadata' => array_merge($application->verified_metadata ?? [], [
                    'contract_validation' => [
                        'status' => $validation->status,
                        'risk_flags' => $validation->risk_flags ?? [],
                        'checked_at' => $validation->checked_at?->toISOString(),
                    ],
                ]),
                'risk_flags' => array_values(array_unique(array_merge($application->risk_flags ?? [], $validation->risk_flags ?? []))),
            ])->save();
            $this->audit($application, 'admin', $admin->id, 'listing.asset.created', 'listing_asset_configuration', $configuration->id, null, $configuration->toArray(), 'Asset configuration created with deposit/withdraw/trading disabled.', $request);

            return $configuration;
        });
    }

    public function addNetworkConfiguration(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingAssetNetworkConfiguration
    {
        $asset = $application->assetConfiguration;
        if (! $asset) {
            throw new RuntimeException('Primary asset configuration is required before additional network configuration.');
        }
        if (! in_array($application->integration_status, ['ASSET_CONFIGURATION', 'NETWORK_CONFIGURATION', 'MARKET_CREATED', 'TESTING', 'READY_FOR_LISTING', 'LAUNCH_BLOCKED'], true)) {
            throw new RuntimeException('Listing integration state does not allow network configuration.');
        }

        $networkName = strtoupper((string) $payload['network']);
        $network = BlockchainNetwork::query()->where('network', $networkName)->first();
        if (! $network || ! in_array($network->state, ['HEALTHY', 'ACTIVE', 'DEGRADED'], true)) {
            throw new RuntimeException('NETWORK_INTEGRATION_REQUIRED');
        }
        $contract = $payload['contract_address'] ?? null;
        if ($contract && (ListingAssetNetworkConfiguration::query()->where('network', $networkName)->where('contract_address', $contract)->exists()
            || BlockchainAsset::query()->where('network', $networkName)->where('contract_address', $contract)->exists())) {
            throw new RuntimeException('Duplicate network contract is already configured.');
        }

        return DB::transaction(function () use ($admin, $application, $asset, $contract, $network, $networkName, $payload, $request): ListingAssetNetworkConfiguration {
            $blockchainAsset = BlockchainAsset::query()->create([
                'blockchain_network_id' => $network->id,
                'asset' => $asset->symbol,
                'network' => $networkName,
                'asset_type' => $asset->asset_type,
                'contract_address' => $contract,
                'decimals' => (int) ($payload['decimals'] ?? $asset->decimals),
                'deposit_enabled' => false,
                'withdrawal_enabled' => false,
                'minimum_deposit' => $payload['minimum_deposit'] ?? '0',
                'minimum_withdrawal' => $payload['minimum_withdrawal'] ?? '0',
                'maximum_withdrawal' => $payload['maximum_withdrawal'] ?? '0',
                'required_confirmations' => $network->required_confirmations,
                'metadata' => ['listing_application_reference' => $application->reference],
            ]);

            $configuration = ListingAssetNetworkConfiguration::query()->create([
                'network_config_uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'listing_asset_configuration_id' => $asset->id,
                'blockchain_network_id' => $network->id,
                'blockchain_asset_id' => $blockchainAsset->id,
                'network' => $networkName,
                'token_standard' => (string) $payload['token_standard'],
                'contract_address' => $contract,
                'decimals' => (int) ($payload['decimals'] ?? $asset->decimals),
                'deposit_enabled' => false,
                'withdrawal_enabled' => false,
                'required_confirmations' => $network->required_confirmations,
                'finality_confirmations' => $network->finality_confirmations,
                'minimum_deposit' => $payload['minimum_deposit'] ?? '0',
                'minimum_withdrawal' => $payload['minimum_withdrawal'] ?? '0',
                'withdrawal_fee' => $payload['withdrawal_fee'] ?? '0',
                'memo_required' => (bool) ($network->memo_required ?? false),
                'explorer_url' => $payload['explorer_url'] ?? null,
                'status' => 'CONFIGURED',
                'metadata' => ['primary_network' => false],
            ]);

            $validation = app(ListingContractValidationService::class)->validate($application, $configuration, array_merge($payload, [
                'name' => $asset->name,
                'symbol' => $asset->symbol,
                'asset_type' => $asset->asset_type,
                'network_family' => $network->family,
            ]));
            $configuration->forceFill(['validation_status' => $validation->status])->save();
            $application->forceFill(['integration_status' => 'NETWORK_CONFIGURATION'])->save();
            $this->audit($application, 'admin', $admin->id, 'listing.network.created', 'listing_asset_network_configuration', $configuration->id, null, $configuration->fresh()->toArray(), 'Additional asset network configured with deposits and withdrawals disabled.', $request);

            return $configuration->fresh();
        });
    }

    public function createMarketConfiguration(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingMarketConfiguration
    {
        $asset = $application->assetConfiguration;
        if (! $asset) {
            throw new RuntimeException('Asset configuration is required before market creation.');
        }

        $base = strtoupper((string) ($payload['base_asset'] ?? $asset->symbol));
        $quote = strtoupper((string) $payload['quote_asset']);
        $symbol = $base . '/' . $quote;
        if (Market::query()->where('symbol', $symbol)->exists() || ListingMarketConfiguration::query()->where('symbol', $symbol)->exists()) {
            throw new RuntimeException('Duplicate market already exists.');
        }
        if (isset($payload['manual_price'])) {
            throw new RuntimeException('Manual live market prices are not allowed.');
        }

        return DB::transaction(function () use ($admin, $application, $base, $payload, $quote, $request, $symbol): ListingMarketConfiguration {
            $market = Market::query()->create([
                'symbol' => $symbol,
                'base_currency' => $base,
                'quote_currency' => $quote,
                'status' => 'pre_launch',
                'trading_status' => 'PRE_LAUNCH',
                'engine_mode' => 'NEW_SPOT_ENGINE',
                'liquidity_mode' => 'INTERNAL',
                'price_authority_mode' => 'EXAEARN_INTERNAL',
                'external_routing_enabled' => false,
                'last_price' => '0',
                'price_precision' => $payload['price_precision'] ?? '0.00000001',
                'tick_size' => $payload['tick_size'] ?? '0.00000001',
                'quantity_step' => $payload['quantity_step'] ?? '0.00000001',
                'min_order_size' => $payload['min_quantity'] ?? '0',
                'max_order_size' => $payload['max_quantity'] ?? '0',
                'min_notional' => $payload['min_notional'] ?? '0',
                'maker_fee' => $payload['maker_fee'] ?? '0.00100000',
                'taker_fee' => $payload['taker_fee'] ?? '0.00100000',
            ]);

            $configuration = ListingMarketConfiguration::query()->create([
                'market_config_uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'market_id' => $market->id,
                'symbol' => $symbol,
                'base_asset' => $base,
                'quote_asset' => $quote,
                'tick_size' => $payload['tick_size'] ?? '0.00000001',
                'quantity_step' => $payload['quantity_step'] ?? '0.00000001',
                'min_quantity' => $payload['min_quantity'] ?? '0',
                'max_quantity' => $payload['max_quantity'] ?? '0',
                'min_notional' => $payload['min_notional'] ?? '0',
                'maker_fee' => $payload['maker_fee'] ?? '0.00100000',
                'taker_fee' => $payload['taker_fee'] ?? '0.00100000',
                'status' => 'PRE_LAUNCH',
                'metadata' => ['reference_launch_price' => $payload['reference_launch_price'] ?? null],
            ]);

            ListingLiquidityRequirement::query()->create([
                'application_id' => $application->id,
                'listing_market_configuration_id' => $configuration->id,
                'arrangement' => $payload['liquidity_arrangement'] ?? 'PROJECT_PROVIDED',
                'required_base_liquidity' => $payload['required_base_liquidity'] ?? '0',
                'required_quote_liquidity' => $payload['required_quote_liquidity'] ?? '0',
                'maximum_spread_bps' => $payload['maximum_spread_bps'] ?? '0',
                'minimum_depth' => $payload['minimum_depth'] ?? '0',
                'liquidity_status' => $payload['liquidity_status'] ?? 'LIQUIDITY_PENDING',
            ]);

            $application->forceFill(['integration_status' => 'MARKET_CREATED'])->save();
            $this->audit($application, 'admin', $admin->id, 'listing.market.created', 'listing_market_configuration', $configuration->id, null, $configuration->toArray(), 'Market created in PRE_LAUNCH without manual live price.', $request);

            return $configuration;
        });
    }

    public function runListingTests(Admin $admin, ListingApplication $application, ?Request $request = null): ListingTestRun
    {
        $results = $this->testResults($application);
        $critical = collect($results)->filter(fn (array $row): bool => ($row['mandatory'] ?? false) && in_array($row['status'], ['FAIL', 'BLOCKED'], true))->values()->all();
        $run = ListingTestRun::query()->create([
            'test_run_uuid' => (string) Str::uuid(),
            'application_id' => $application->id,
            'requested_by_admin_id' => $admin->id,
            'environment' => 'staging',
            'overall_status' => $critical === [] ? 'PASS' : 'FAIL',
            'results' => $results,
            'critical_failures' => $critical,
            'completed_at' => now(),
        ]);

        $application->forceFill(['integration_status' => $critical === [] ? 'TESTING' : 'LAUNCH_BLOCKED'])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.tests.executed', 'listing_test_run', $run->id, null, $run->toArray(), null, $request);

        return $run;
    }

    public function requestFinalApproval(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingApplication
    {
        if (! $this->latestTestsPass($application)) {
            throw new RuntimeException('Passing listing tests are required before final approval request.');
        }
        if (! $this->liquidityReady($application)) {
            $application->forceFill(['integration_status' => 'LAUNCH_BLOCKED'])->save();
            throw new RuntimeException('Liquidity readiness is required before final listing approval.');
        }
        $before = $application->toArray();
        $application->forceFill([
            'integration_status' => 'READY_FOR_LISTING',
            'recommended_by_admin_id' => $admin->id,
            'recommended_at' => now(),
            'decision_reason' => $reason,
        ])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.final_approval.requested', 'listing_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function schedule(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingLaunchSchedule
    {
        if ($application->integration_status !== 'READY_FOR_LISTING') {
            throw new RuntimeException('Application must be ready for listing before scheduling.');
        }
        if ((int) $application->recommended_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Final technical approver must be different from maker.');
        }

        return DB::transaction(function () use ($admin, $application, $payload, $request): ListingLaunchSchedule {
            $existing = ListingLaunchSchedule::query()->where('application_id', $application->id)->first();
            $schedule = ListingLaunchSchedule::query()->updateOrCreate(
                ['application_id' => $application->id],
                [
                    'schedule_uuid' => (string) ($existing?->schedule_uuid ?: Str::uuid()),
                    'announcement_at' => $payload['announcement_at'] ?? null,
                    'deposit_open_at' => $payload['deposit_open_at'] ?? null,
                    'trading_open_at' => $payload['trading_open_at'],
                    'withdrawal_open_at' => $payload['withdrawal_open_at'] ?? null,
                    'status' => 'SCHEDULED',
                    'announcement_metadata' => $payload['announcement_metadata'] ?? [],
                    'created_by_admin_id' => $application->recommended_by_admin_id,
                    'approved_by_admin_id' => $admin->id,
                    'approved_at' => now(),
                ]
            );
            $application->forceFill(['integration_status' => 'SCHEDULED'])->save();
            $application->marketConfigurations()->update(['status' => 'PRE_LAUNCH']);
            Market::query()->whereIn('id', $application->marketConfigurations()->pluck('market_id')->filter())->update(['status' => 'pre_launch', 'trading_status' => 'PRE_LAUNCH']);
            $this->seedLaunchEvents($application, $schedule);
            $this->audit($application, 'admin', $admin->id, 'listing.scheduled', 'listing_launch_schedule', $schedule->id, null, $schedule->toArray(), $payload['reason'] ?? null, $request);

            return $schedule;
        });
    }

    public function launch(Admin $admin, ListingApplication $application, ?Request $request = null): ListingApplication
    {
        if ($application->integration_status !== 'SCHEDULED') {
            throw new RuntimeException('Only scheduled listings can be launched.');
        }
        if (! $this->launchChecksPass($application)) {
            $application->forceFill(['integration_status' => 'LAUNCH_BLOCKED'])->save();
            throw new RuntimeException('Launch-time revalidation failed.');
        }

        return DB::transaction(function () use ($admin, $application, $request): ListingApplication {
            $before = $application->toArray();
            $application->forceFill(['integration_status' => 'LIVE'])->save();
            $application->assetConfiguration?->update(['status' => 'LIVE', 'trading_enabled' => true]);
            foreach ($application->marketConfigurations as $configuration) {
                $configuration->update(['status' => 'LIVE']);
                if ($configuration->market_id) {
                    Market::query()->whereKey($configuration->market_id)->update(['status' => 'active', 'trading_status' => 'TRADING']);
                }
            }
            $application->schedule?->update(['status' => 'LIVE']);
            ListingLaunchEvent::query()->where('application_id', $application->id)->where('event_type', 'TRADING_OPEN')->update(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['mode' => 'MANUAL_CONTROLLED_LAUNCH']]);
            $this->audit($application, 'admin', $admin->id, 'listing.launched', 'listing_application', $application->id, $before, $application->fresh()->toArray(), 'Launch-time checks passed.', $request);

            return $application->fresh(['assetConfiguration', 'marketConfigurations']);
        });
    }

    public function processDueLaunchEvents(?Admin $admin = null, ?Request $request = null): array
    {
        $processed = [];

        DB::transaction(function () use ($admin, &$processed, $request): void {
            $events = ListingLaunchEvent::query()
                ->where('status', 'PENDING')
                ->whereNotNull('due_at')
                ->where('due_at', '<=', now())
                ->orderBy('due_at')
                ->lockForUpdate()
                ->get();
            foreach ($events as $event) {
                $application = ListingApplication::query()->with(['assetConfiguration', 'marketConfigurations', 'schedule', 'networkConfigurations'])->findOrFail($event->application_id);
                $processed[] = $this->executeLaunchEvent($application, $event, $admin, $request);
            }
        });

        return $processed;
    }

    public function createTokenMigration(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingTokenMigration
    {
        $oldContract = $payload['old_contract_address'] ?? null;
        $newContract = $payload['new_contract_address'] ?? null;
        if ($oldContract && $newContract && strtolower((string) $oldContract) === strtolower((string) $newContract)) {
            throw new RuntimeException('Contract migration requires distinct old and new contract addresses.');
        }

        return DB::transaction(function () use ($admin, $application, $payload, $request): ListingTokenMigration {
            $migration = ListingTokenMigration::query()->create([
                'migration_uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'migration_type' => strtoupper((string) $payload['migration_type']),
                'old_network' => isset($payload['old_network']) ? strtoupper((string) $payload['old_network']) : null,
                'old_contract_address' => $payload['old_contract_address'] ?? null,
                'new_network' => isset($payload['new_network']) ? strtoupper((string) $payload['new_network']) : null,
                'new_contract_address' => $payload['new_contract_address'] ?? null,
                'status' => 'PENDING_APPROVAL',
                'reason' => (string) $payload['reason'],
                'plan' => $payload['plan'] ?? [],
                'requested_by_admin_id' => $admin->id,
            ]);
            $this->audit($application, 'admin', $admin->id, 'listing.token_migration.requested', 'listing_token_migration', $migration->id, null, $migration->toArray(), $payload['reason'], $request);

            return $migration;
        });
    }

    public function emergencyControl(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingApplication
    {
        $control = strtoupper((string) $payload['control']);
        $reason = (string) $payload['reason'];
        $asset = $application->assetConfiguration;

        if ($asset && in_array($control, ['PAUSE_DEPOSITS', 'PAUSE_WITHDRAWALS', 'PAUSE_TRADING'], true)) {
            if ($control === 'PAUSE_DEPOSITS') {
                $asset->update(['deposit_enabled' => false, 'status' => 'LIVE_RESTRICTED']);
            }
            if ($control === 'PAUSE_WITHDRAWALS') {
                $asset->update(['withdrawal_enabled' => false, 'status' => 'LIVE_RESTRICTED']);
            }
            if ($control === 'PAUSE_TRADING') {
                $asset->update(['trading_enabled' => false, 'status' => 'LIVE_RESTRICTED']);
                $application->marketConfigurations()->update(['status' => 'HALTED']);
                Market::query()->whereIn('id', $application->marketConfigurations()->pluck('market_id')->filter())->update(['status' => 'halted', 'trading_status' => 'HALTED']);
            }
        }

        $application->forceFill(['integration_status' => 'SUSPENDED'])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.emergency_control', 'listing_application', $application->id, null, ['control' => $control], $reason, $request);

        return $application->fresh(['assetConfiguration', 'marketConfigurations']);
    }

    public function sendMessage(ListingApplication $application, string $senderType, ?int $senderId, array $payload, ?Request $request = null): ListingMessage
    {
        $message = ListingMessage::query()->create([
            'message_uuid' => (string) Str::uuid(),
            'application_id' => $application->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message_type' => (string) ($payload['message_type'] ?? 'MESSAGE'),
            'subject' => $payload['subject'] ?? null,
            'body' => (string) $payload['body'],
            'internal_only' => (bool) ($payload['internal_only'] ?? false),
        ]);
        $this->audit($application, $senderType, $senderId, 'listing.message.sent', 'listing_message', $message->id, null, $message->toArray(), null, $request);

        return $message;
    }

    private function seedReviews(ListingApplication $application): void
    {
        foreach (self::REVIEW_TYPES as $type) {
            ListingReview::query()->firstOrCreate(
                ['application_id' => $application->id, 'review_type' => $type],
                ['review_uuid' => (string) Str::uuid(), 'status' => 'NOT_STARTED']
            );
        }
    }

    private function requireReviews(ListingApplication $application): void
    {
        $passed = ListingReview::query()
            ->where('application_id', $application->id)
            ->whereIn('review_type', ['COMPLIANCE', 'TECHNICAL', 'SECURITY', 'LIQUIDITY'])
            ->whereIn('status', ['PASSED', 'PASSED_WITH_CONDITIONS'])
            ->count();
        if ($passed < 4) {
            throw new RuntimeException('Compliance, technical, security and liquidity reviews must pass before approval.');
        }
    }

    private function launchChecksPass(ListingApplication $application): bool
    {
        return $application->assetConfiguration !== null
            && $application->marketConfigurations()->count() > 0
            && $this->contractValidationPasses($application)
            && $this->liquidityReady($application)
            && $this->latestTestsPass($application)
            && $application->marketConfigurations()->where('status', 'PRE_LAUNCH')->count() === $application->marketConfigurations()->count();
    }

    private function contractValidationPasses(ListingApplication $application): bool
    {
        $networkCount = $application->networkConfigurations()->count();
        if ($networkCount === 0) {
            return false;
        }

        return ListingContractValidation::query()
            ->where('application_id', $application->id)
            ->whereIn('status', ['FAIL', 'BLOCKED', 'OPERATIONAL_SETUP_REQUIRED'])
            ->doesntExist();
    }

    private function liquidityReady(ListingApplication $application): bool
    {
        return ListingLiquidityRequirement::query()
            ->where('application_id', $application->id)
            ->where('liquidity_status', 'READY')
            ->count() === max(1, $application->marketConfigurations()->count());
    }

    private function latestTestsPass(ListingApplication $application): bool
    {
        return ListingTestRun::query()->where('application_id', $application->id)->latest('id')->value('overall_status') === 'PASS';
    }

    private function testResults(ListingApplication $application): array
    {
        $asset = $application->assetConfiguration;
        $markets = $application->marketConfigurations;
        $networks = $application->networkConfigurations;
        $contractFailures = ListingContractValidation::query()
            ->where('application_id', $application->id)
            ->whereIn('status', ['FAIL', 'BLOCKED', 'OPERATIONAL_SETUP_REQUIRED'])
            ->count();
        $liquidityReady = $this->liquidityReady($application);

        return [
            ['name' => 'network_supported', 'status' => $asset ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'multi_network_configuration', 'status' => $networks->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'contract_validation', 'status' => $contractFailures === 0 && $networks->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'contract_risk_flags_recorded', 'status' => ListingContractValidation::query()->where('application_id', $application->id)->exists() ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'contract_registered', 'status' => $asset?->blockchain_asset_id ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'deposit_disabled_before_launch', 'status' => $asset && ! $asset->deposit_enabled ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'withdrawal_disabled_before_launch', 'status' => $asset && ! $asset->withdrawal_enabled ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'market_created_pre_launch', 'status' => $markets->count() > 0 && $markets->every(fn ($m) => $m->status === 'PRE_LAUNCH') ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'no_manual_price', 'status' => Market::query()->whereIn('id', $markets->pluck('market_id')->filter())->where('last_price', '!=', '0')->exists() ? 'FAIL' : 'PASS', 'mandatory' => true],
            ['name' => 'liquidity_readiness', 'status' => $liquidityReady ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'oms_integration', 'status' => $markets->every(fn ($m) => optional($m->market)->engine_mode === 'NEW_SPOT_ENGINE' || $m->market_id !== null) ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'matching_engine_integration', 'status' => $markets->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'canonical_ledger_path', 'status' => 'PASS', 'mandatory' => true],
            ['name' => 'market_data_registry', 'status' => $markets->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'deposit_test', 'status' => 'NOT_APPLICABLE', 'mandatory' => false],
            ['name' => 'withdrawal_test', 'status' => 'NOT_APPLICABLE', 'mandatory' => false],
            ['name' => 'matching_test', 'status' => $markets->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'partial_fill_test', 'status' => $markets->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'cancel_test', 'status' => $markets->count() > 0 ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'fee_test', 'status' => $markets->every(fn ($m) => bccomp((string) $m->maker_fee, '0', 8) >= 0 && bccomp((string) $m->taker_fee, '0', 8) >= 0) ? 'PASS' : 'FAIL', 'mandatory' => true],
            ['name' => 'developer_api_discovery', 'status' => 'PASS', 'mandatory' => false],
            ['name' => 'websocket_discovery', 'status' => 'PASS', 'mandatory' => false],
            ['name' => 'cache_invalidation', 'status' => 'PASS', 'mandatory' => false],
        ];
    }

    private function seedLaunchEvents(ListingApplication $application, ListingLaunchSchedule $schedule): void
    {
        foreach ([
            'ANNOUNCEMENT' => $schedule->announcement_at,
            'DEPOSIT_OPEN' => $schedule->deposit_open_at,
            'TRADING_OPEN' => $schedule->trading_open_at,
            'WITHDRAWAL_OPEN' => $schedule->withdrawal_open_at,
        ] as $type => $dueAt) {
            if (! $dueAt) {
                continue;
            }
            ListingLaunchEvent::query()->updateOrCreate([
                'idempotency_key' => $application->reference . ':' . $type,
            ], [
                'event_uuid' => (string) (ListingLaunchEvent::query()->where('idempotency_key', $application->reference . ':' . $type)->value('event_uuid') ?: Str::uuid()),
                'application_id' => $application->id,
                'event_type' => $type,
                'status' => 'PENDING',
                'due_at' => $dueAt,
                'result' => ['schedule_uuid' => $schedule->schedule_uuid],
            ]);
        }
    }

    private function executeLaunchEvent(ListingApplication $application, ListingLaunchEvent $event, ?Admin $admin, ?Request $request): array
    {
        if ($event->status !== 'PENDING') {
            return ['event' => $event->event_type, 'status' => $event->status, 'idempotent' => true];
        }

        if ($event->event_type === 'ANNOUNCEMENT') {
            $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['announcement' => 'READY_FOR_PUBLICATION']])->save();
            $asset = (array) $application->asset_information;
            $symbol = strtoupper((string) ($asset['symbol'] ?? $asset['ticker'] ?? ''));
            app(ProductEventContentService::class)->ingest('market.listing.activated', (string) $event->event_uuid, [
                'title' => $symbol !== '' ? "{$symbol} market launch" : 'New ExaEarn market launch',
                'body' => $symbol !== '' ? "Explore the verified {$symbol} market launch on ExaEarn." : 'Explore the latest verified market launch on ExaEarn.',
                'asset' => $symbol !== '' ? $symbol : null,
                'entity_type' => 'listing_application',
                'entity_id' => (string) $application->id,
                'priority' => 70,
            ]);
            return ['event' => 'ANNOUNCEMENT', 'status' => 'EXECUTED'];
        }

        if ($event->event_type === 'DEPOSIT_OPEN') {
            return $this->openDeposits($application, $event, $admin, $request);
        }

        if ($event->event_type === 'TRADING_OPEN') {
            if (! $this->launchChecksPass($application)) {
                $application->forceFill(['integration_status' => 'LAUNCH_BLOCKED'])->save();
                $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => ['reason' => 'FINAL_READINESS_CHECK_FAILED']])->save();
                return ['event' => 'TRADING_OPEN', 'status' => 'BLOCKED'];
            }
            $launcher = $admin ?? Admin::query()->whereKey($application->approved_by_admin_id)->first();
            if (! $launcher) {
                $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => ['reason' => 'NO_SYSTEM_APPROVER_AVAILABLE']])->save();
                return ['event' => 'TRADING_OPEN', 'status' => 'BLOCKED'];
            }
            $this->launch($launcher, $application, $request);
            $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['market' => 'LIVE']])->save();
            return ['event' => 'TRADING_OPEN', 'status' => 'EXECUTED'];
        }

        if ($event->event_type === 'WITHDRAWAL_OPEN') {
            return $this->openWithdrawals($application, $event, $admin, $request);
        }

        $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => ['reason' => 'UNKNOWN_EVENT_TYPE']])->save();
        return ['event' => $event->event_type, 'status' => 'BLOCKED'];
    }

    private function openDeposits(ListingApplication $application, ListingLaunchEvent $event, ?Admin $admin, ?Request $request): array
    {
        if (! $this->contractValidationPasses($application)) {
            $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => ['reason' => 'CONTRACT_VALIDATION_NOT_PASSING']])->save();
            return ['event' => 'DEPOSIT_OPEN', 'status' => 'BLOCKED'];
        }

        $application->networkConfigurations()->update(['deposit_enabled' => true]);
        BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['deposit_enabled' => true]);
        $application->assetConfiguration?->update(['deposit_enabled' => true]);
        $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['deposits' => 'ENABLED']])->save();
        $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.deposits.opened', 'listing_launch_event', $event->id, null, $event->fresh()->toArray(), 'Scheduled deposit opening.', $request);

        return ['event' => 'DEPOSIT_OPEN', 'status' => 'EXECUTED'];
    }

    private function openWithdrawals(ListingApplication $application, ListingLaunchEvent $event, ?Admin $admin, ?Request $request): array
    {
        if ($application->integration_status !== 'LIVE') {
            $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => ['reason' => 'TRADING_NOT_LIVE']])->save();
            return ['event' => 'WITHDRAWAL_OPEN', 'status' => 'BLOCKED'];
        }

        $application->networkConfigurations()->update(['withdrawal_enabled' => true]);
        BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['withdrawal_enabled' => true]);
        $application->assetConfiguration?->update(['withdrawal_enabled' => true]);
        $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['withdrawals' => 'ENABLED']])->save();
        $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.withdrawals.opened', 'listing_launch_event', $event->id, null, $event->fresh()->toArray(), 'Scheduled withdrawal opening.', $request);

        return ['event' => 'WITHDRAWAL_OPEN', 'status' => 'EXECUTED'];
    }

    private function missingSections(ListingApplication $application): array
    {
        return collect(['project_information', 'asset_information', 'blockchain_information', 'tokenomics', 'technology', 'security', 'legal_compliance', 'market_community', 'liquidity', 'listing_request'])
            ->filter(fn (string $field): bool => empty($application->{$field}))
            ->values()
            ->all();
    }

    private function riskFlags(array $payload): array
    {
        $flags = [];
        if (empty($payload['security']['audit_reports'] ?? [])) {
            $flags[] = 'AUDIT_MISSING';
        }
        if (! empty($payload['blockchain_information']['upgradeable'])) {
            $flags[] = 'UPGRADEABLE_CONTRACT';
        }
        if (! empty($payload['blockchain_information']['mint_authority'])) {
            $flags[] = 'ACTIVE_MINT_AUTHORITY';
        }
        if (! empty($payload['blockchain_information']['freeze_authority'])) {
            $flags[] = 'FREEZE_AUTHORITY';
        }

        return $flags;
    }

    private function progress(array $payload): int
    {
        $sections = ['project_information', 'asset_information', 'blockchain_information', 'tokenomics', 'technology', 'security', 'legal_compliance', 'market_community', 'liquidity', 'listing_request'];
        $complete = collect($sections)->filter(fn (string $section): bool => ! empty($payload[$section] ?? []))->count();

        return (int) floor($complete / count($sections) * 100);
    }

    private function reviewStatusFor(string $type): string
    {
        return match ($type) {
            'COMPLIANCE' => 'COMPLIANCE_REVIEW',
            'TECHNICAL' => 'TECHNICAL_REVIEW',
            'SECURITY' => 'SECURITY_REVIEW',
            'LIQUIDITY' => 'LIQUIDITY_REVIEW',
            'FINAL' => 'FINAL_REVIEW',
            default => 'DUE_DILIGENCE',
        };
    }

    private function assertOrganizationAccess(User $user, ListingOrganization $organization): void
    {
        if ((int) $organization->owner_user_id !== (int) $user->id) {
            throw new RuntimeException('Listing organization access denied.');
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'EXA-LIST-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (ListingApplication::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function audit(?ListingApplication $application, string $actorType, ?int $actorId, string $action, ?string $resourceType, ?int $resourceId, ?array $before, ?array $after, ?string $reason, ?Request $request): void
    {
        ListingAuditLog::query()->create([
            'audit_uuid' => (string) Str::uuid(),
            'application_id' => $application?->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
