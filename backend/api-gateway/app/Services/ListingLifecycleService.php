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
use App\Models\ListingDocument;
use App\Models\ListingDepositActivationApproval;
use App\Models\ListingLaunchEvent;
use App\Models\ListingLaunchSchedule;
use App\Models\ListingLiquidityRequirement;
use App\Models\ListingMarketConfiguration;
use App\Models\ListingMessage;
use App\Models\ListingOrganization;
use App\Models\ListingReview;
use App\Models\ListingReviewAssignment;
use App\Models\ListingReviewDecisionEvent;
use App\Models\ListingTestRun;
use App\Models\ListingTokenMigration;
use App\Models\ListingWithdrawalActivationApproval;
use App\Models\Market;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use App\Services\PersonalizedContent\ProductEventContentService;

class ListingLifecycleService
{
    public const REVIEW_TYPES = ['INITIAL', 'DUE_DILIGENCE', 'COMPLIANCE', 'TECHNICAL', 'SECURITY', 'LIQUIDITY', 'FINAL'];
    public const MATERIAL_FIELDS = ['project_information', 'asset_information', 'blockchain_information', 'tokenomics', 'technology', 'security', 'legal_compliance', 'market_community', 'liquidity', 'listing_request'];
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
        $this->assertOrganizationAccess($user, $organization, ['OWNER', 'ADMIN', 'CONTRIBUTOR']);
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existing = ListingApplication::query()->where('organization_id', $organization->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->application_status !== 'DRAFT' && $existing->application_status !== 'INFORMATION_REQUESTED') {
                    throw new RuntimeException('Only draft or information-requested applications may be edited.');
                }
                $before = $existing->toArray();
                $fields = array_merge(['application_type'], self::MATERIAL_FIELDS);
                $updates = collect($fields)->mapWithKeys(fn (string $field): array => array_key_exists($field, $payload) ? [$field => $payload[$field]] : [])->all();
                if (collect(self::MATERIAL_FIELDS)->contains(fn (string $field): bool => array_key_exists($field, $updates) && $updates[$field] !== $existing->{$field})) {
                    $updates['application_revision'] = ((int) ($existing->application_revision ?? 1)) + 1;
                    $updates['recommended_by_admin_id'] = null;
                    $updates['recommended_at'] = null;
                    $updates['recommended_revision'] = null;
                    $this->invalidateCurrentReviews($existing, 'MATERIAL_APPLICANT_CHANGE');
                }
                $updates['progress_percent'] = $this->progress(array_merge($existing->toArray(), $payload));
                $updates['risk_flags'] = $this->riskFlags(array_merge($existing->toArray(), $payload));
                $existing->fill($updates)->save();
                $this->audit($existing, 'user', $user->id, 'listing.application.draft_saved', 'listing_application', $existing->id, $before, $existing->fresh()->toArray(), null, $request);

                return $existing->fresh();
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
                'application_revision' => 1,
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
        $this->assertOrganizationAccess($user, $application->organization, ['OWNER', 'ADMIN']);
        if ((bool) config('listing.require_approved_kyb_for_listing_submission') && $application->organization->kyb_status !== 'APPROVED') {
            throw new RuntimeException('Organization KYB approval is required before a listing application can be submitted.');
        }
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
        $this->assertSemanticApplicationFields($application);

        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'SUBMITTED',
            'submitted_at' => now(),
            'progress_percent' => 100,
            'application_revision' => ((int) ($application->application_revision ?? 1)) + 1,
        ])->save();
        $this->seedReviews($application);
        $this->audit($application, 'user', $user->id, 'listing.application.submitted', 'listing_application', $application->id, $before, $application->fresh()->toArray(), null, $request);

        return $application->fresh(['reviews']);
    }

    public function assignReview(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingReviewAssignment
    {
        $type = strtoupper((string) $payload['review_domain']);
        if (! in_array($type, self::REVIEW_TYPES, true)) {
            throw new RuntimeException('Unsupported review domain.');
        }

        $reviewer = Admin::query()->findOrFail((int) $payload['reviewer_admin_id']);
        $this->assertReviewerEligible($reviewer, $application, $type);

        return DB::transaction(function () use ($admin, $application, $payload, $request, $reviewer, $type): ListingReviewAssignment {
            $previous = ListingReviewAssignment::query()
                ->where('application_id', $application->id)
                ->where('review_domain', $type)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->first();

            if ($previous && (int) $previous->reviewer_admin_id === (int) $reviewer->id) {
                return $previous;
            }

            if ($previous) {
                $previous->update(['status' => 'REASSIGNED', 'reassignment_reason' => (string) $payload['reason']]);
            }

            $assignment = ListingReviewAssignment::query()->create([
                'assignment_uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'review_domain' => $type,
                'reviewer_admin_id' => $reviewer->id,
                'assigned_by_admin_id' => $admin->id,
                'assigned_at' => now(),
                'status' => 'ACTIVE',
                'reassignment_reason' => $payload['reason'] ?? null,
            ]);

            ListingReview::query()->updateOrCreate(
                ['application_id' => $application->id, 'review_type' => $type],
                [
                    'review_uuid' => (string) (ListingReview::query()->where('application_id', $application->id)->where('review_type', $type)->value('review_uuid') ?: Str::uuid()),
                    'reviewer_admin_id' => $reviewer->id,
                    'assigned_by_admin_id' => $admin->id,
                    'assigned_at' => now(),
                    'assignment_reason' => $payload['reason'] ?? null,
                ]
            );

            $this->audit($application, 'admin', $admin->id, $previous ? 'listing.review.reassigned' : 'listing.review.assigned', 'listing_review_assignment', $assignment->id, $previous?->toArray(), $assignment->toArray(), $payload['reason'] ?? null, $request);

            return $assignment;
        });
    }

    public function completeReview(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingReview
    {
        $type = strtoupper((string) $payload['review_type']);
        if (! in_array($type, self::REVIEW_TYPES, true)) {
            throw new RuntimeException('Unsupported review type.');
        }
        $this->assertExpectedRevision($application, $payload['expected_revision'] ?? null);
        $this->assertReviewerEligible($admin, $application, $type);
        $this->assertReviewAssignment($admin, $application, $type);

        return DB::transaction(function () use ($admin, $application, $payload, $request, $type): ListingReview {
            $application = ListingApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $this->assertExpectedRevision($application, $payload['expected_revision'] ?? null);
            $review = ListingReview::query()->where('application_id', $application->id)->where('review_type', $type)->lockForUpdate()->first()
                ?: ListingReview::query()->create(['application_id' => $application->id, 'review_type' => $type, 'review_uuid' => (string) Str::uuid()]);
            $before = $review->toArray();
            if (in_array($review->status, ['PASSED', 'PASSED_WITH_CONDITIONS', 'FAILED'], true) && (int) $review->application_revision === (int) $application->application_revision) {
                throw new RuntimeException('REVIEW_ALREADY_DECIDED');
            }
            $fingerprint = $this->governanceFingerprint($application);
            $review->fill([
                'reviewer_admin_id' => $admin->id,
                'status' => (string) $payload['status'],
                'application_revision' => (int) $application->application_revision,
                'configuration_fingerprint' => $fingerprint,
                'score' => $payload['score'] ?? null,
                'scorecard' => $payload['scorecard'] ?? [],
                'risk_flags' => $payload['risk_flags'] ?? [],
                'notes' => $payload['notes'] ?? null,
                'completed_at' => now(),
            ])->save();

            $application->forceFill([
                'risk_flags' => array_values(array_unique(array_merge($application->risk_flags ?? [], $review->risk_flags ?? []))),
                'application_status' => $this->reviewStatusFor($type),
                'governance_fingerprint' => $fingerprint,
            ])->save();

            $this->decisionEvent($application, $review, $admin, $type, (string) $payload['status'], $payload['notes'] ?? null, ['scorecard' => $payload['scorecard'] ?? [], 'risk_flags' => $payload['risk_flags'] ?? []], $fingerprint);
            $this->audit($application, 'admin', $admin->id, 'listing.review.completed', 'listing_review', $review->id, $before, $review->fresh()->toArray(), $payload['notes'] ?? null, $request);

            return $review->fresh();
        });
    }

    public function recommendApproval(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingApplication
    {
        if (! in_array($application->application_status, ['FINAL_REVIEW', 'LIQUIDITY_REVIEW', 'CONDITIONALLY_APPROVED', 'SUBMITTED'], true)) {
            throw new RuntimeException('Application is not ready for recommendation.');
        }
        $this->requireReviews($application);
        $this->assertReviewerEligible($admin, $application, 'FINAL');
        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'FINAL_REVIEW',
            'recommended_by_admin_id' => $admin->id,
            'recommended_at' => now(),
            'recommended_revision' => (int) ($application->application_revision ?? 1),
            'decision_reason' => $reason,
        ])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.application.recommended', 'listing_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function requestInformation(Admin $admin, ListingApplication $application, array $payload, ?Request $request = null): ListingMessage
    {
        if (in_array($application->application_status, ['APPROVED', 'REJECTED', 'WITHDRAWN'], true)) {
            throw new RuntimeException('Information cannot be requested from a terminal application state.');
        }
        $this->assertReviewerEligible($admin, $application, 'INITIAL');
        $before = $application->toArray();
        $application->forceFill(['application_status' => 'INFORMATION_REQUESTED', 'application_revision' => ((int) ($application->application_revision ?? 1)) + 1, 'recommended_by_admin_id' => null, 'recommended_at' => null, 'recommended_revision' => null])->save();
        $this->invalidateCurrentReviews($application, 'INFORMATION_REQUESTED');
        $message = $this->sendMessage($application, 'admin', $admin->id, [
            'message_type' => 'INFORMATION_REQUEST',
            'subject' => $payload['subject'],
            'body' => $payload['body'],
            'internal_only' => false,
        ], $request);
        $this->audit($application, 'admin', $admin->id, 'listing.information.requested', 'listing_application', $application->id, $before, $application->fresh()->toArray(), $payload['body'], $request);

        return $message;
    }

    public function rejectApplication(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingApplication
    {
        if (in_array($application->application_status, ['APPROVED', 'REJECTED', 'WITHDRAWN'], true)) {
            throw new RuntimeException('Application cannot be rejected from its current state.');
        }
        $this->assertReviewerEligible($admin, $application, 'FINAL');
        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'REJECTED',
            'rejected_by_admin_id' => $admin->id,
            'rejected_at' => now(),
            'decision_reason' => $reason,
        ])->save();
        $this->audit($application, 'admin', $admin->id, 'listing.application.rejected', 'listing_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function approveApplication(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingApplication
    {
        app(ListingDocumentSecurityService::class)->assertListingDocumentsClean($application);
        if (! $application->recommended_by_admin_id) {
            throw new RuntimeException('Maker recommendation is required before approval.');
        }
        if ((int) $application->recommended_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Senior approver must be different from recommendation maker.');
        }
        if ((int) ($application->recommended_revision ?? 0) !== (int) ($application->application_revision ?? 1)) {
            throw new RuntimeException('STALE_REVISION');
        }
        $this->assertReviewerEligible($admin, $application, 'FINAL');
        $before = $application->toArray();
        $application->forceFill([
            'application_status' => 'APPROVED',
            'integration_status' => 'INTEGRATION',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'approved_revision' => (int) ($application->application_revision ?? 1),
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
            $market = app(MarketActivationService::class)->createConfiguration([
                'symbol' => $symbol,
                'base_currency' => $base,
                'quote_currency' => $quote,
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
            ], MarketActivationService::LISTING_GOVERNED, $application);

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
        $this->audit($application, 'admin', $admin->id, 'listing.certification.started', 'listing_application', $application->id, null, ['certification_version' => ListingIntegrationCertificationService::VERSION], null, $request);
        $results = app(ListingIntegrationCertificationService::class)->run($application);
        if (! $this->liquidityReady($application)) {
            $prototype = $results[0] ?? [];
            $results[] = array_merge($prototype, [
                'test_name' => 'liquidity_precondition',
                'status' => 'BLOCKED',
                'required' => true,
                'evidence' => [],
                'error_code' => 'LIQUIDITY_NOT_READY',
                'error_message' => 'Persisted liquidity requirements are not ready; executable liquidity verification remains a separate production gate.',
            ]);
        }
        $critical = collect($results)->filter(fn (array $row): bool => ($row['required'] ?? false) && $row['status'] !== 'PASS')->values()->all();
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
        $this->audit($application, 'admin', $admin->id, $run->overall_status === 'PASS' ? 'listing.certification.completed' : 'listing.certification.failed', 'listing_test_run', $run->id, null, ['overall_status' => $run->overall_status, 'correlation_id' => data_get($results, '0.correlation_id'), 'configuration_fingerprint' => data_get($results, '0.configuration_fingerprint')], null, $request);

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

    public function launch(Admin $admin, ListingApplication $application, ?Request $request = null, ?ListingLaunchEvent $launchEvent = null): ListingApplication
    {
        if ($application->integration_status !== 'SCHEDULED') {
            throw new RuntimeException('Only scheduled listings can be launched.');
        }
        if (! $this->launchChecksPass($application)) {
            $application->forceFill(['integration_status' => 'LAUNCH_BLOCKED'])->save();
            throw new RuntimeException('Launch-time revalidation failed.');
        }

        return DB::transaction(function () use ($admin, $application, $request, $launchEvent): ListingApplication {
            $before = $application->toArray();
            foreach ($application->marketConfigurations as $configuration) {
                if ($configuration->market_id) app(MarketActivationService::class)->activateListing($application, $configuration, $launchEvent, $admin);
                $configuration->update(['status' => 'LIVE']);
            }
            $application->assetConfiguration?->update(['status' => 'LIVE', 'trading_enabled' => true]);
            $application->forceFill(['integration_status' => 'LIVE'])->save();
            $application->schedule?->update(['status' => 'LIVE']);
            ListingLaunchEvent::query()->where('application_id', $application->id)->where('event_type', 'TRADING_OPEN')->update(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['mode' => 'MANUAL_CONTROLLED_LAUNCH']]);
            $this->audit($application, 'admin', $admin->id, 'listing.launched', 'listing_application', $application->id, $before, $application->fresh()->toArray(), 'Launch-time checks passed.', $request);

            return $application->fresh(['assetConfiguration', 'marketConfigurations']);
        });
    }

    public function assertMarketActivationReady(ListingApplication $application): void
    {
        if (! $this->launchChecksPass($application)) {
            throw new RuntimeException('Launch-time governance revalidation failed.');
        }
    }

    public function processDueLaunchEvents(?Admin $admin = null, ?Request $request = null): array
    {
        return app(ListingLaunchDispatcherService::class)->dispatchDue();
    }

    public function executeDurableLaunchEvent(int $eventId, string $claimToken): array
    {
        $event = DB::transaction(function () use ($eventId, $claimToken): ?ListingLaunchEvent {
            $event = ListingLaunchEvent::query()->lockForUpdate()->findOrFail($eventId);
            if (in_array($event->operation_status, ListingLaunchEvent::TERMINAL_STATES, true)) {
                return null;
            }
            if ($event->claim_token !== $claimToken) {
                return null;
            }
            if (! in_array($event->operation_status, ListingLaunchEvent::WORKER_CLAIMABLE_STATES, true)) {
                return null;
            }

            $event->forceFill([
                // This transition is the non-cancellable execution boundary.
                'operation_status' => ListingLaunchEvent::EXECUTING,
                'attempt_count' => ((int) $event->attempt_count) + 1,
                'started_at' => $event->started_at ?: now(),
                'last_heartbeat_at' => now(),
                'lease_expires_at' => now()->addSeconds(max(30, (int) config('listing.launch.execution_lease_seconds', 180))),
                'failure_code' => null,
                'failure_reason' => null,
            ])->save();
            return $event;
        });

        if (! $event) {
            return ['status' => 'NOOP', 'idempotent' => true];
        }

        try {
            $application = ListingApplication::query()->with([
                'organization', 'assetConfiguration', 'marketConfigurations', 'schedule', 'networkConfigurations',
            ])->findOrFail($event->application_id);
            $currentFingerprint = app(ListingIntegrationCertificationService::class)->fingerprint($application);
            if (! hash_equals((string) $event->configuration_fingerprint, $currentFingerprint)) {
                return $this->blockDurableEvent($event, 'CONFIGURATION_FINGERPRINT_MISMATCH', 'Material Listing configuration changed after scheduling.');
            }
            if (! $this->protectedLaunchGatesPass($application)) {
                return $this->blockDurableEvent($event, 'EXECUTION_REVALIDATION_FAILED', 'Current approval, KYB, document, certification, or readiness controls are not passing.');
            }

            $result = $this->executeLaunchEvent($application, $event, null, null);
            $event->refresh();
            $application->refresh()->load(['assetConfiguration', 'marketConfigurations', 'networkConfigurations']);
            if ($event->status === 'EXECUTED' && ! $this->launchPostconditionsPass($application, $event)) {
                $event->forceFill([
                    'operation_status' => ListingLaunchEvent::PARTIALLY_FAILED, 'failure_code' => 'POSTCONDITION_VERIFICATION_FAILED',
                    'failure_reason' => 'Canonical launch state does not match the intended capability scope.',
                    'completed_at' => now(), 'lease_expires_at' => null,
                ])->save();
                return ['event' => $event->event_type, 'status' => 'PARTIALLY_FAILED'];
            }
            $terminal = $event->status === 'EXECUTED' ? ListingLaunchEvent::SUCCEEDED : ListingLaunchEvent::BLOCKED;
            $event->forceFill([
                'operation_status' => $terminal,
                'completed_at' => now(),
                'lease_expires_at' => null,
                'failure_code' => $terminal === 'BLOCKED' ? (string) data_get($event->result, 'reason', 'READINESS_BLOCKED') : null,
            ])->save();
            return $result;
        } catch (Throwable $exception) {
            $event->refresh();
            $exhausted = (int) $event->attempt_count >= max(1, (int) config('listing.launch.max_attempts', 3));
            $event->forceFill([
                'operation_status' => $exhausted ? ListingLaunchEvent::FAILED : ListingLaunchEvent::RETRY_PENDING,
                'failure_code' => $exhausted ? 'RETRY_EXHAUSTED' : 'TRANSIENT_EXECUTION_FAILURE',
                'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => $exhausted ? now() : null,
                'lease_expires_at' => null,
            ])->save();
            throw $exception;
        }
    }

    public function cancelLaunchEvent(Admin $admin, ListingLaunchEvent $event, string $reason, ?Request $request = null): ListingLaunchEvent
    {
        return DB::transaction(function () use ($admin, $event, $reason, $request): ListingLaunchEvent {
            $locked = ListingLaunchEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($locked->operation_status === ListingLaunchEvent::EXECUTING) {
                throw new RuntimeException('EXECUTION_IN_PROGRESS: launch operation is too late to cancel.');
            }
            if (! in_array($locked->operation_status, ListingLaunchEvent::CANCELLABLE_STATES, true)) {
                throw new RuntimeException('Terminal launch operations cannot be cancelled.');
            }
            $locked->forceFill([
                'operation_status' => ListingLaunchEvent::CANCELLED, 'status' => 'CANCELLED', 'completed_at' => now(),
                'lease_expires_at' => null, 'failure_code' => 'CANCELLED_BY_OPERATOR', 'failure_reason' => $reason,
            ])->save();
            $application = ListingApplication::query()->findOrFail($locked->application_id);
            $this->audit($application, 'admin', $admin->id, 'listing.launch.cancelled', 'listing_launch_event', $locked->id, null, $locked->toArray(), $reason, $request);
            return $locked->fresh();
        });
    }

    public function depositReadiness(ListingApplication $application, ?Admin $admin = null): array
    {
        $result = app(ListingDepositReadinessService::class)->evaluate($application, $admin);
        $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.deposit_readiness.checked', 'listing_application', $application->id, null, $result, null, null);
        return $result;
    }

    public function requestDepositActivation(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingDepositActivationApproval
    {
        $approval = app(ListingDepositReadinessService::class)->requestApproval($application, $admin, $reason);
        $this->audit($application, 'admin', $admin->id, 'listing.deposit_activation.requested', 'listing_deposit_activation_approval', $approval->id, null, $approval->toArray(), $reason, $request);
        return $approval;
    }

    public function approveDepositActivation(Admin $admin, ListingApplication $application, ?Request $request = null): ListingDepositActivationApproval
    {
        $approval = app(ListingDepositReadinessService::class)->approve($application, $admin);
        $this->audit($application, 'admin', $admin->id, 'listing.deposit_activation.approved', 'listing_deposit_activation_approval', $approval->id, null, $approval->toArray(), $approval->reason, $request);
        return $approval;
    }

    public function withdrawalReadiness(ListingApplication $application, ?Admin $admin = null): array
    {
        $result = app(ListingWithdrawalReadinessService::class)->evaluate($application, $admin);
        $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.withdrawal_readiness.checked', 'listing_application', $application->id, null, $result, null, null);
        return $result;
    }

    public function requestWithdrawalActivation(Admin $admin, ListingApplication $application, string $reason, ?Request $request = null): ListingWithdrawalActivationApproval
    {
        $approval = app(ListingWithdrawalReadinessService::class)->requestApproval($application, $admin, $reason);
        $this->audit($application, 'admin', $admin->id, 'listing.withdrawal_activation.requested', 'listing_withdrawal_activation_approval', $approval->id, null, $approval->toArray(), $reason, $request);
        return $approval;
    }

    public function approveWithdrawalActivation(Admin $admin, ListingApplication $application, ?Request $request = null): ListingWithdrawalActivationApproval
    {
        $approval = app(ListingWithdrawalReadinessService::class)->approve($application, $admin);
        $this->audit($application, 'admin', $admin->id, 'listing.withdrawal_activation.approved', 'listing_withdrawal_activation_approval', $approval->id, null, $approval->toArray(), $approval->reason, $request);
        return $approval;
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

        if ($control === 'RESUME_DEPOSITS') {
            $decision = app(ListingDepositReadinessService::class)->activationDecision($application);
            if (! $decision['approved']) {
                throw new RuntimeException('Deposit resume requires current readiness and a fresh dual approval.');
            }
            DB::transaction(function () use ($application, $asset, $decision): void {
                $asset?->update(['deposit_enabled' => true, 'status' => 'LIVE']);
                $application->networkConfigurations()->update(['deposit_enabled' => true]);
                BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['deposit_enabled' => true]);
                $decision['approval']->update(['status' => 'CONSUMED', 'consumed_at' => now()]);
                $application->forceFill(['integration_status' => 'LIVE'])->save();
            });
            $this->audit($application, 'admin', $admin->id, 'listing.deposits.resumed', 'listing_application', $application->id, null, ['configuration_fingerprint' => $decision['configuration_fingerprint']], $reason, $request);
            return $application->fresh(['assetConfiguration', 'networkConfigurations']);
        }

        if ($control === 'RESUME_WITHDRAWALS') {
            $decision = app(ListingWithdrawalReadinessService::class)->activationDecision($application);
            if (! $decision['approved']) {
                throw new RuntimeException('Withdrawal resume requires current readiness and a fresh dual approval.');
            }
            DB::transaction(function () use ($application, $asset, $decision): void {
                $asset?->update(['withdrawal_enabled' => true, 'status' => 'LIVE']);
                $application->networkConfigurations()->update(['withdrawal_enabled' => true]);
                BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['withdrawal_enabled' => true]);
                $decision['approval']->update(['status' => 'CONSUMED', 'consumed_at' => now()]);
                $application->forceFill(['integration_status' => 'LIVE'])->save();
            });
            $this->audit($application, 'admin', $admin->id, 'listing.withdrawals.resumed', 'listing_application', $application->id, null, ['configuration_fingerprint' => $decision['configuration_fingerprint']], $reason, $request);
            return $application->fresh(['assetConfiguration', 'networkConfigurations']);
        }

        if ($asset && in_array($control, ['PAUSE_DEPOSITS', 'PAUSE_WITHDRAWALS', 'PAUSE_TRADING'], true)) {
            if ($control === 'PAUSE_DEPOSITS') {
                $asset->update(['deposit_enabled' => false, 'status' => 'LIVE_RESTRICTED']);
                $application->networkConfigurations()->update(['deposit_enabled' => false]);
                BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['deposit_enabled' => false]);
                ListingDepositActivationApproval::query()->where('application_id', $application->id)->whereIn('status', ['REQUESTED', 'APPROVED'])->update(['status' => 'INVALIDATED']);
            }
            if ($control === 'PAUSE_WITHDRAWALS') {
                $asset->update(['withdrawal_enabled' => false, 'status' => 'LIVE_RESTRICTED']);
                $application->networkConfigurations()->update(['withdrawal_enabled' => false]);
                BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['withdrawal_enabled' => false]);
                ListingWithdrawalActivationApproval::query()->where('application_id', $application->id)->whereIn('status', ['REQUESTED', 'APPROVED'])->update(['status' => 'INVALIDATED']);
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

    public function storeDocument(User $user, ListingApplication $application, array $payload, ?Request $request = null): ListingDocument
    {
        $this->assertOrganizationAccess($user, $application->organization, ['OWNER', 'ADMIN', 'CONTRIBUTOR']);
        $file = $payload['file'];
        $type = strtoupper((string) $payload['document_type']);
        $version = ((int) ListingDocument::query()->where('application_id', $application->id)->where('document_type', $type)->max('version')) + 1;
        $uuid = (string) Str::uuid();
        $stored = app(ListingDocumentSecurityService::class)->storeQuarantined($file, 'listing', (string) $application->application_uuid, $uuid);

        $document = ListingDocument::query()->create([
            'document_uuid' => $uuid,
            'application_id' => $application->id,
            'uploaded_by' => $user->id,
            'document_type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'storage_disk' => $stored['storage_disk'],
            'storage_path' => $stored['storage_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'visibility' => 'PRIVATE',
            'scan_status' => 'QUARANTINED',
            'checksum_sha256' => $stored['checksum_sha256'],
            'version' => $version,
            'review_status' => 'PENDING',
            'metadata' => ['scanner_adapter' => config('listing.malware_scanner_adapter', 'not_connected')],
        ]);
        $before = $application->toArray();
        $application->forceFill([
            'application_revision' => ((int) ($application->application_revision ?? 1)) + 1,
            'recommended_by_admin_id' => null,
            'recommended_at' => null,
            'recommended_revision' => null,
        ])->save();
        $this->invalidateCurrentReviews($application->fresh(), 'DOCUMENT_REPLACED');
        $this->audit($application, 'user', $user->id, 'listing.document.uploaded', 'listing_document', $document->id, null, $document->toArray(), null, $request);
        $this->audit($application, 'system', null, 'listing.application.revision_changed', 'listing_application', $application->id, $before, $application->fresh()->toArray(), 'DOCUMENT_REPLACED', $request);
        app(ListingDocumentSecurityService::class)->queue('listing', $document);

        return $document;
    }

    public function documentDownload(User $user, ListingDocument $document): string
    {
        $this->assertOrganizationAccess($user, $document->application->organization);
        app(ListingDocumentSecurityService::class)->assertDownloadable($document);
        if (! Storage::disk($document->storage_disk)->exists($document->storage_path)) {
            throw new RuntimeException('Document file is unavailable.');
        }

        return Storage::disk($document->storage_disk)->path($document->storage_path);
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
            ->where('application_revision', (int) ($application->application_revision ?? 1))
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
        $run = ListingTestRun::query()->where('application_id', $application->id)->latest('id')->first();
        if (! $run || $run->overall_status !== 'PASS') {
            return false;
        }

        $fingerprint = app(ListingIntegrationCertificationService::class)->fingerprint($application);
        $results = collect($run->results ?? []);

        return $results->isNotEmpty()
            && $results->every(fn (array $result): bool => ($result['configuration_fingerprint'] ?? null) === $fingerprint)
            && $results->where('required', true)->every(fn (array $result): bool => ($result['status'] ?? null) === 'PASS');
    }

    private function testResults(ListingApplication $application): array
    {
        return app(ListingIntegrationCertificationService::class)->run($application);
    }

    private function assertExpectedRevision(ListingApplication $application, mixed $expectedRevision): void
    {
        if ($expectedRevision !== null && (int) $expectedRevision !== (int) ($application->application_revision ?? 1)) {
            throw new RuntimeException('STALE_REVISION');
        }
    }

    private function assertReviewerEligible(Admin $admin, ListingApplication $application, string $domain): void
    {
        if ($admin->status !== 'active') {
            throw new RuntimeException('Admin account is disabled.');
        }

        $canonicalUserId = $admin->canonical_user_id ? (int) $admin->canonical_user_id : null;
        if (! $canonicalUserId) {
            return;
        }

        $organization = $application->organization;
        if ((int) $organization->owner_user_id === $canonicalUserId
            || $organization->teamMembers()->where('user_id', $canonicalUserId)->where('status', 'ACTIVE')->exists()
            || $organization->people()->where('linked_user_id', $canonicalUserId)->exists()) {
            throw new RuntimeException('CONFLICT_OF_INTEREST');
        }
    }

    private function assertReviewAssignment(Admin $admin, ListingApplication $application, string $type): void
    {
        $assignment = ListingReviewAssignment::query()
            ->where('application_id', $application->id)
            ->where('review_domain', $type)
            ->where('status', 'ACTIVE')
            ->latest('id')
            ->first();

        if ($assignment && (int) $assignment->reviewer_admin_id !== (int) $admin->id) {
            throw new RuntimeException('REVIEW_ASSIGNMENT_REQUIRED');
        }

        if (! $assignment && ! $admin->hasPermission('listing.review.assign')) {
            throw new RuntimeException('REVIEW_ASSIGNMENT_REQUIRED');
        }
    }

    private function invalidateCurrentReviews(ListingApplication $application, string $reason): void
    {
        ListingReview::query()
            ->where('application_id', $application->id)
            ->whereIn('status', ['PASSED', 'PASSED_WITH_CONDITIONS'])
            ->update(['status' => 'NOT_STARTED', 'completed_at' => null]);

        $this->audit($application, 'system', null, 'listing.approvals.invalidated', 'listing_application', $application->id, null, [
            'reason' => $reason,
            'application_revision' => (int) ($application->application_revision ?? 1),
        ], $reason, null);
    }

    private function governanceFingerprint(ListingApplication $application): string
    {
        return hash('sha256', json_encode([
            'application_revision' => (int) ($application->application_revision ?? 1),
            'material' => collect(self::MATERIAL_FIELDS)->mapWithKeys(fn (string $field): array => [$field => $application->{$field}])->all(),
            'documents' => $application->documents()->orderBy('document_type')->orderByDesc('version')->get(['document_type', 'version', 'checksum_sha256', 'scan_status'])->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    private function decisionEvent(ListingApplication $application, ListingReview $review, Admin $admin, string $domain, string $decision, ?string $reason, array $evidence, ?string $fingerprint): void
    {
        ListingReviewDecisionEvent::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'application_id' => $application->id,
            'review_id' => $review->id,
            'review_domain' => $domain,
            'reviewer_admin_id' => $admin->id,
            'decision' => $decision,
            'reason' => $reason,
            'evidence' => $evidence,
            'application_revision' => (int) ($application->application_revision ?? 1),
            'configuration_fingerprint' => $fingerprint,
            'decided_at' => now(),
        ]);
    }

    private function seedLaunchEvents(ListingApplication $application, ListingLaunchSchedule $schedule): void
    {
        $fingerprint = app(ListingIntegrationCertificationService::class)->fingerprint($application);
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
                'operation_status' => 'SCHEDULED',
                'due_at' => $dueAt,
                'configuration_fingerprint' => $fingerprint,
                'launch_scope' => [$type],
                'correlation_id' => (string) Str::uuid(),
                'attempt_count' => 0,
                'claim_token' => null,
                'lease_expires_at' => null,
                'result' => ['schedule_uuid' => $schedule->schedule_uuid, 'configuration_fingerprint' => $fingerprint],
            ]);
        }
    }

    private function executeLaunchEvent(ListingApplication $application, ListingLaunchEvent $event, ?Admin $admin, ?Request $request): array
    {
        if ($event->status !== 'PENDING' && $event->operation_status !== 'EXECUTING') {
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
            $this->launch($launcher, $application, $request, $event);
            $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['market' => 'LIVE']])->save();
            return ['event' => 'TRADING_OPEN', 'status' => 'EXECUTED'];
        }

        if ($event->event_type === 'WITHDRAWAL_OPEN') {
            return $this->openWithdrawals($application, $event, $admin, $request);
        }

        $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => ['reason' => 'UNKNOWN_EVENT_TYPE']])->save();
        return ['event' => $event->event_type, 'status' => 'BLOCKED'];
    }

    private function protectedLaunchGatesPass(ListingApplication $application): bool
    {
        try {
            if ($application->application_status !== 'APPROVED') {
                return false;
            }
            if ((bool) config('listing.require_approved_kyb_for_listing_submission', true)) {
                if ($application->organization?->kyb_status !== 'APPROVED') {
                    return false;
                }
                app(ListingDocumentSecurityService::class)->assertKybDocumentsClean($application->organization);
            }
            app(ListingDocumentSecurityService::class)->assertListingDocumentsClean($application);
            return $this->launchChecksPass($application);
        } catch (Throwable) {
            return false;
        }
    }

    private function blockDurableEvent(ListingLaunchEvent $event, string $code, string $reason): array
    {
        $event->forceFill([
            'operation_status' => 'BLOCKED', 'status' => 'BLOCKED', 'failure_code' => $code,
            'failure_reason' => $reason, 'completed_at' => now(), 'executed_at' => now(), 'lease_expires_at' => null,
            'result' => array_merge((array) $event->result, ['reason' => $code]),
        ])->save();
        return ['event' => $event->event_type, 'status' => 'BLOCKED', 'reason' => $code];
    }

    private function launchPostconditionsPass(ListingApplication $application, ListingLaunchEvent $event): bool
    {
        return match ($event->event_type) {
            'ANNOUNCEMENT' => $event->status === 'EXECUTED',
            'TRADING_OPEN' => $application->integration_status === 'LIVE'
                && (bool) $application->assetConfiguration?->trading_enabled
                && $application->marketConfigurations->isNotEmpty()
                && $application->marketConfigurations->every(fn (ListingMarketConfiguration $market): bool => $market->status === 'LIVE')
                && Market::query()->whereIn('id', $application->marketConfigurations->pluck('market_id')->filter())
                    ->where(fn ($query) => $query->where('status', '!=', 'active')->orWhere('trading_status', '!=', 'TRADING'))->doesntExist(),
            'DEPOSIT_OPEN' => (bool) $application->assetConfiguration?->deposit_enabled
                && $application->networkConfigurations->every(fn (ListingAssetNetworkConfiguration $network): bool => (bool) $network->deposit_enabled),
            'WITHDRAWAL_OPEN' => (bool) $application->assetConfiguration?->withdrawal_enabled
                && $application->networkConfigurations->every(fn (ListingAssetNetworkConfiguration $network): bool => (bool) $network->withdrawal_enabled),
            default => false,
        };
    }

    private function openDeposits(ListingApplication $application, ListingLaunchEvent $event, ?Admin $admin, ?Request $request): array
    {
        $decision = app(ListingDepositReadinessService::class)->activationDecision($application);
        if (! $this->contractValidationPasses($application) || ! $decision['approved']) {
            $reason = ! $this->contractValidationPasses($application) ? 'CONTRACT_VALIDATION_NOT_PASSING' : 'DEPOSIT_READINESS_OR_APPROVAL_NOT_PASSING';
            $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => [
                'reason' => $reason,
                'blocking_gates' => $decision['blocking_gates'],
                'configuration_fingerprint' => $decision['configuration_fingerprint'],
            ]])->save();
            $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.deposit_activation.blocked', 'listing_launch_event', $event->id, null, $event->fresh()->toArray(), $reason, $request);
            return ['event' => 'DEPOSIT_OPEN', 'status' => 'BLOCKED'];
        }

        DB::transaction(function () use ($application, $decision, $event): void {
            $application->networkConfigurations()->update(['deposit_enabled' => true]);
            BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['deposit_enabled' => true]);
            $application->assetConfiguration?->update(['deposit_enabled' => true]);
            $decision['approval']->update(['status' => 'CONSUMED', 'consumed_at' => now()]);
            $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['deposits' => 'ENABLED', 'configuration_fingerprint' => $decision['configuration_fingerprint']]])->save();
        });
        $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.deposits.opened', 'listing_launch_event', $event->id, null, $event->fresh()->toArray(), 'Scheduled deposit opening.', $request);

        return ['event' => 'DEPOSIT_OPEN', 'status' => 'EXECUTED'];
    }

    private function openWithdrawals(ListingApplication $application, ListingLaunchEvent $event, ?Admin $admin, ?Request $request): array
    {
        $decision = app(ListingWithdrawalReadinessService::class)->activationDecision($application);
        if (! $decision['approved']) {
            $event->forceFill(['status' => 'BLOCKED', 'executed_at' => now(), 'result' => [
                'reason' => 'WITHDRAWAL_READINESS_OR_APPROVAL_NOT_PASSING',
                'blocking_gates' => $decision['blocking_gates'],
                'configuration_fingerprint' => $decision['configuration_fingerprint'],
            ]])->save();
            $this->audit($application, $admin ? 'admin' : 'system', $admin?->id, 'listing.withdrawal_activation.blocked', 'listing_launch_event', $event->id, null, $event->fresh()->toArray(), 'Withdrawal readiness failed at execution.', $request);
            return ['event' => 'WITHDRAWAL_OPEN', 'status' => 'BLOCKED'];
        }

        DB::transaction(function () use ($application, $decision, $event): void {
            $application->networkConfigurations()->update(['withdrawal_enabled' => true]);
            BlockchainAsset::query()->whereIn('id', $application->networkConfigurations()->pluck('blockchain_asset_id')->filter())->update(['withdrawal_enabled' => true]);
            $application->assetConfiguration?->update(['withdrawal_enabled' => true]);
            $decision['approval']->update(['status' => 'CONSUMED', 'consumed_at' => now()]);
            $event->forceFill(['status' => 'EXECUTED', 'executed_at' => now(), 'result' => ['withdrawals' => 'ENABLED', 'configuration_fingerprint' => $decision['configuration_fingerprint']]])->save();
        });
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

    private function assertSemanticApplicationFields(ListingApplication $application): void
    {
        $data = [
            'project_information' => (array) $application->project_information,
            'asset_information' => (array) $application->asset_information,
            'blockchain_information' => (array) $application->blockchain_information,
            'tokenomics' => (array) $application->tokenomics,
            'technology' => (array) $application->technology,
            'security' => (array) $application->security,
            'legal_compliance' => (array) $application->legal_compliance,
            'market_community' => (array) $application->market_community,
            'liquidity' => (array) $application->liquidity,
            'listing_request' => (array) $application->listing_request,
        ];

        $validator = Validator::make($data, [
            'project_information.name' => ['required', 'string', 'max:140'],
            'asset_information.name' => ['required', 'string', 'max:140'],
            'asset_information.symbol' => ['required', 'string', 'regex:/^[A-Z0-9]{2,12}$/'],
            'blockchain_information.network' => ['required', 'string', 'max:64'],
            'blockchain_information.contract_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'blockchain_information.token_standard' => ['required', 'string', 'max:40'],
            'tokenomics.total_supply' => ['required', 'numeric', 'gt:0'],
            'technology.repository' => ['required', 'url', 'max:255'],
            'security.audit_reports' => ['required', 'array', 'min:1'],
            'security.audit_reports.*' => ['required', 'url', 'max:255'],
            'legal_compliance' => ['required', 'array', 'min:1'],
            'market_community.website' => ['required', 'url', 'max:255'],
            'liquidity' => ['required', 'array', 'min:1'],
            'listing_request.requested_pairs' => ['required', 'array', 'min:1'],
            'listing_request.requested_pairs.*' => ['required', 'string', 'regex:/^[A-Z0-9]{2,12}\\/[A-Z0-9]{2,12}$/'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Application semantic validation failed: '.$validator->errors()->first());
        }
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

    public function assertOrganizationAccess(User $user, ListingOrganization $organization, array $roles = ['OWNER', 'ADMIN', 'CONTRIBUTOR', 'VIEWER']): void
    {
        $role = (int) $organization->owner_user_id === (int) $user->id
            ? 'OWNER'
            : $organization->teamMembers()->where('user_id', $user->id)->where('status', 'ACTIVE')->value('role');
        if (! $role || ! in_array($role, $roles, true)) {
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
