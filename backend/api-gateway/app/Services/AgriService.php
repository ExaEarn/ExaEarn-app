<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AgriTech\AgriInvestmentService;
use App\Jobs\CalculateHarvestReturnsJob;
use App\Jobs\DistributeInvestorRewardsJob;
use App\Jobs\VerifyFarmReportsJob;
use App\Models\AgriReward;
use App\Models\Farmer;
use App\Models\FarmInvestment;
use App\Models\FarmLease;
use App\Models\FarmShare;
use App\Models\FarmingProject;
use App\Models\ProduceTracking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgriService
{
    public function __construct(
        private readonly BlockchainService $blockchainService,
        private readonly RewardEngineService $rewardEngineService,
        private readonly AgriInvestmentService $investmentService,
    ) {
    }

    public function projects(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return FarmingProject::query()
            ->with(['share', 'leases.farmer', 'produceUpdates' => fn ($query) => $query->latest('recorded_at')->limit(5)])
            ->withCount(['investments', 'produceUpdates'])
            ->when(!empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['crop_type']), fn (Builder $query) => $query->where('crop_type', $filters['crop_type']))
            ->when(!empty($filters['location']), fn (Builder $query) => $query->where('location', 'like', '%' . trim((string) $filters['location']) . '%'))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function project(int $projectId): FarmingProject
    {
        return FarmingProject::query()
            ->with([
                'share',
                'leases.farmer',
                'produceUpdates' => fn ($query) => $query->latest('recorded_at'),
            ])
            ->findOrFail($projectId);
    }

    public function projectDashboard(int $projectId): array
    {
        $project = $this->project($projectId);
        $share = $project->share;
        $invested = (string) $project->investments()
            ->where('financial_status', 'SETTLED_IN_ESCROW')
            ->sum('investment_amount');

        return [
            'project' => $project,
            'funding' => [
                'target' => $project->investment_target,
                'raised' => $invested,
                'shares_available' => $share?->shares_available,
                'total_shares' => $share?->total_shares,
            ],
            'progress' => $project->produceUpdates->map(fn (ProduceTracking $update) => [
                'id' => $update->id,
                'growth_stage' => $update->growth_stage,
                'update_description' => $update->update_description,
                'recorded_at' => $update->recorded_at,
                'verification_status' => $update->verification_status,
                'reported_yield' => $update->reported_yield,
            ]),
        ];
    }

    public function myInvestments(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return FarmInvestment::query()
            ->with(['project.share', 'leases.farmer'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function farmers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Farmer::query()
            ->with('user:id,name,email')
            ->when(!empty($filters['verification_status']), fn (Builder $query) => $query->where('verification_status', $filters['verification_status']))
            ->latest()
            ->paginate($perPage);
    }

    public function produceFeed(int $projectId): Collection
    {
        return ProduceTracking::query()
            ->where('project_id', $projectId)
            ->latest('recorded_at')
            ->get();
    }

    public function createProject(User $user, array $payload): FarmingProject
    {
        $this->assertRole($user, ['admin']);

        return DB::transaction(function () use ($user, $payload): FarmingProject {
            $project = FarmingProject::query()->create([
                'created_by' => $user->id,
                'project_name' => $payload['project_name'],
                'location' => $payload['location'],
                'crop_type' => $payload['crop_type'],
                'farm_size' => $payload['farm_size'],
                'farm_size_unit' => $payload['farm_size_unit'] ?? 'acres',
                'investment_target' => $payload['investment_target'],
                'duration' => $payload['duration'],
                'duration_unit' => $payload['duration_unit'] ?? 'months',
                'expected_yield' => $payload['expected_yield'],
                'yield_unit' => $payload['yield_unit'] ?? 'tons',
                'expected_harvest_date' => $payload['expected_harvest_date'] ?? null,
                'status' => $payload['status'] ?? 'draft',
                'verification_documents' => $payload['verification_documents'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
                'product_type' => strtoupper((string) ($payload['product_type'] ?? 'FARM_PROJECT')),
                'economic_type' => strtoupper((string) ($payload['economic_type'] ?? 'NON_INVESTMENT_SUPPORT')),
                'currency' => strtoupper((string) ($payload['currency'] ?? config('agriculture.financial.default_asset', 'USDT'))),
                'legal_status' => strtoupper((string) ($payload['legal_status'] ?? 'PENDING_REVIEW')),
                'verification_status' => strtoupper((string) ($payload['verification_status'] ?? 'UNVERIFIED')),
                'public_funding_enabled' => (bool) ($payload['public_funding_enabled'] ?? false),
                'funding_deadline' => $payload['funding_deadline'] ?? null,
                'risk_disclosures' => $payload['risk_disclosures'] ?? null,
                'settlement_policy' => $payload['settlement_policy'] ?? null,
            ]);

            $share = FarmShare::query()->create([
                'project_id' => $project->id,
                'total_shares' => $payload['total_shares'],
                'price_per_share' => $payload['price_per_share'],
                'shares_available' => $payload['total_shares'],
                'ownership_model' => $payload['ownership_model'] ?? 'hybrid',
                'token_symbol' => $payload['token_symbol'] ?? null,
                'metadata' => $payload['share_metadata'] ?? null,
            ]);

            if (
                (bool) config('agriculture.blockchain.enabled')
                && (bool) config('agriculture.tokenized_investment_enabled', false)
                && $project->economic_type === 'TOKENIZED_INVESTMENT'
                && $project->legal_status === 'APPROVED'
            ) {
                $tokenization = $this->blockchainService->tokenizeFarmProject([
                    'project_id' => $project->id,
                    'project_name' => $project->project_name,
                    'investment_target' => (string) $project->investment_target,
                    'total_shares' => $share->total_shares,
                    'price_per_share' => (string) $share->price_per_share,
                    'token_symbol' => $share->token_symbol,
                ]);

                $project->blockchain_reference = $tokenization['project_reference'] ?? $tokenization['tx_hash'] ?? null;
                $project->save();

                $share->token_contract_address = $tokenization['token_contract_address'] ?? null;
                $share->metadata = array_merge($share->metadata ?? [], ['tokenization' => $tokenization]);
                $share->save();
            }

            return $project->fresh(['share']);
        });
    }

    public function invest(User $user, int $projectId, array $payload): FarmInvestment
    {
        return $this->investmentService->purchase(
            $user,
            $projectId,
            (int) $payload['shares_owned'],
            (string) ($payload['idempotency_key'] ?? ''),
            (array) ($payload['context'] ?? []),
        );
    }

    public function applyFarmer(User $user, array $payload): Farmer
    {
        return Farmer::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $payload['name'] ?? $user->name,
                'location' => $payload['location'],
                'experience_years' => $payload['experience_years'],
                'verification_status' => 'pending',
                'state' => 'APPLIED',
                'identity_status' => $user->kyc_verified_at ? 'VERIFIED' : 'PENDING',
                'land_verification_status' => 'PENDING',
                'identity_documents' => $payload['identity_documents'] ?? null,
                'equipment_details' => $payload['equipment_details'] ?? null,
                'geo_metadata' => $payload['geo_metadata'] ?? null,
                'bio' => $payload['bio'] ?? null,
            ]
        );
    }

    public function reviewFarmer(User $user, int $farmerId, string $status): Farmer
    {
        $this->assertRole($user, ['admin']);

        $farmer = Farmer::query()->findOrFail($farmerId);
        $state = strtoupper($status);
        if (!in_array($state, ['UNDER_REVIEW', 'NEEDS_INFORMATION', 'VERIFIED', 'APPROVED', 'SUSPENDED', 'REJECTED', 'DEACTIVATED'], true)) {
            throw new RuntimeException('Invalid farmer review state.');
        }
        if ($state === 'APPROVED' && ($farmer->identity_status !== 'VERIFIED' || $farmer->land_verification_status !== 'VERIFIED')) {
            throw new RuntimeException('Identity and land verification must be completed before farmer approval.');
        }
        $farmer->state = $state;
        $farmer->verification_status = strtolower($state);
        $farmer->reviewed_by = $user->id;
        $farmer->reviewed_at = now();
        $farmer->save();

        if ($status === 'approved' && $farmer->user_id) {
            $reward = $this->rewardEngineService->issueReward(
                $farmer->user_id,
                (string) config('agriculture.rewards.farmer_support_activity', 'agriculture_reward'),
                '10',
                [
                    'activity_key' => 'agri-farmer-approved:' . $farmer->id,
                    'farmer_id' => $farmer->id,
                    'reward_amount_override' => '10',
                ]
            );

            AgriReward::query()->create([
                'user_id' => $farmer->user_id,
                'activity_type' => 'farmer_onboarding',
                'reward_amount' => $reward->reward_amount,
                'status' => $reward->status,
                'reward_reference' => (string) $reward->id,
                'metadata' => ['user_reward_id' => $reward->id],
            ]);
        }

        return $farmer;
    }

    public function createLease(User $user, int $projectId, array $payload): FarmLease
    {
        $project = FarmingProject::query()->findOrFail($projectId);
        $farmer = Farmer::query()->findOrFail((int) $payload['farmer_id']);
        if ($farmer->verification_status !== 'approved') {
            throw new RuntimeException('Farmer must be approved before leasing.');
        }

        $investmentId = $payload['investment_id'] ?? null;
        if ($user->role !== 'admin') {
            $this->assertRole($user, ['investor', 'admin']);
            if (!$investmentId) {
                throw new RuntimeException('Investors must provide an investment to lease.');
            }

            $ownsInvestment = FarmInvestment::query()
                ->whereKey($investmentId)
                ->where('project_id', $projectId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$ownsInvestment) {
                throw new RuntimeException('You cannot lease an investment you do not own.');
            }
        }

        return DB::transaction(function () use ($user, $project, $farmer, $payload, $investmentId): FarmLease {
            $lease = FarmLease::query()->create([
                'project_id' => $project->id,
                'farmer_id' => $farmer->id,
                'investment_id' => $investmentId,
                'assigned_by' => $user->id,
                'lease_terms' => $payload['lease_terms'],
                'profit_share' => (int) $payload['profit_share'],
                'starts_on' => $payload['starts_on'] ?? null,
                'ends_on' => $payload['ends_on'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'metadata' => $payload['metadata'] ?? null,
            ]);

            if ((bool) config('agriculture.blockchain.enabled')) {
                $contract = $this->blockchainService->registerFarmLease([
                    'project_id' => $project->id,
                    'farmer_id' => $farmer->id,
                    'investment_id' => $investmentId,
                    'profit_share' => $lease->profit_share,
                    'lease_terms' => $lease->lease_terms,
                ]);

                $lease->contract_reference = $contract['contract_reference'] ?? $contract['tx_hash'] ?? null;
                $lease->metadata = array_merge($lease->metadata ?? [], ['blockchain' => $contract]);
                $lease->save();
            }

            if ($project->status === 'funded') {
                $project->status = 'active';
                $project->save();
            }

            return $lease->fresh(['project', 'farmer']);
        });
    }

    public function addProduceUpdate(User $user, int $projectId, array $payload): ProduceTracking
    {
        $defaultFarmerId = Farmer::query()->where('user_id', $user->id)->value('id');
        $farmerId = (int) ($payload['farmer_id'] ?? $defaultFarmerId ?? 0);
        $farmer = Farmer::query()->findOrFail($farmerId);

        $hasLease = FarmLease::query()
            ->where('project_id', $projectId)
            ->where('farmer_id', $farmer->id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($user->role !== 'admin' && !$hasLease) {
            throw new RuntimeException('Farmer is not assigned to this project.');
        }

        $update = ProduceTracking::query()->create([
            'project_id' => $projectId,
            'farmer_id' => $farmer->id,
            'growth_stage' => $payload['growth_stage'],
            'update_description' => $payload['update_description'],
            'images' => $payload['images'] ?? null,
            'geo_metadata' => $payload['geo_metadata'] ?? null,
            'reported_yield' => $payload['reported_yield'] ?? null,
            'recorded_at' => $payload['recorded_at'] ?? now(),
            'verification_status' => 'pending_review',
            'metadata' => $payload['metadata'] ?? null,
        ]);

        VerifyFarmReportsJob::dispatch($update->id)->onQueue('agriculture');

        return $update;
    }

    public function queueHarvestSettlement(User $user, int $projectId, array $payload): void
    {
        throw new RuntimeException('Legacy harvest settlement is disabled. Use verified harvest revenue settlement.');
    }

    public function calculateHarvestReturns(int $projectId, string $grossRevenue, string $costs): void
    {
        $project = FarmingProject::query()->findOrFail($projectId);
        $netRevenue = $this->sub($grossRevenue, $costs);
        $project->metadata = array_merge($project->metadata ?? [], [
            'last_harvest_calculation' => [
                'gross_revenue' => $grossRevenue,
                'costs' => $costs,
                'net_revenue' => $netRevenue,
                'calculated_at' => now()->toISOString(),
            ],
        ]);
        $project->save();
    }

    public function distributeHarvestReturns(int $projectId, string $grossRevenue, string $costs): void
    {
        throw new RuntimeException('Legacy projected reward distribution is disabled. Verified revenue is required.');
    }

    public function verifyProduceUpdate(int $trackingId): void
    {
        $update = ProduceTracking::query()->findOrFail($trackingId);
        $geoMetadata = $update->geo_metadata ?? [];
        $hasCoordinates = isset($geoMetadata['lat'], $geoMetadata['lng']);
        $hasImages = count($update->images ?? []) > 0;

        $update->verification_status = 'pending_external_verification';
        $update->metadata = array_merge($update->metadata ?? [], [
            'verified_at' => now()->toISOString(),
            'auto_checks' => [
                'has_coordinates' => $hasCoordinates,
                'has_images' => $hasImages,
                'note' => 'Automated completeness checks do not verify agricultural truth.',
            ],
        ]);
        $update->save();
    }

    private function assertRole(User $user, array $roles): void
    {
        if (!in_array((string) $user->role, $roles, true)) {
            throw new RuntimeException('Your role cannot perform this action.');
        }
    }

    private function mul(string $left, string $right): string
    {
        return FinancialDecimal::mul($left, $right);
    }

    private function sub(string $left, string $right): string
    {
        return FinancialDecimal::sub($left, $right);
    }

    private function div(string $left, string $right): string
    {
        return FinancialDecimal::div($left, $right);
    }
}
