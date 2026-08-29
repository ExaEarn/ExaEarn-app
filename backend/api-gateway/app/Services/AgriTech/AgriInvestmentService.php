<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\FarmInvestment;
use App\Models\FarmShare;
use App\Models\FarmingProject;
use App\Models\LedgerTransaction;
use App\Models\PricingDecision;
use App\Models\User;
use App\Services\CompliancePolicyService;
use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use App\Services\ReservationService;
use App\Services\SecurityRiskEngine;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgriInvestmentService
{
    private const FINANCIAL_TYPES = ['INVESTMENT', 'REVENUE_SHARE', 'TOKENIZED_INVESTMENT'];

    public function __construct(
        private readonly CompliancePolicyService $compliance,
        private readonly SecurityRiskEngine $security,
        private readonly PricingPolicyEngine $pricing,
        private readonly ReservationService $reservations,
        private readonly SettlementService $settlements,
    ) {
    }

    public function purchase(User $user, int $projectId, int $requestedShares, string $idempotencyKey, array $context = []): FarmInvestment
    {
        if ($idempotencyKey === '') {
            throw new RuntimeException('An idempotency key is required.');
        }

        return DB::transaction(function () use ($context, $idempotencyKey, $projectId, $requestedShares, $user): FarmInvestment {
            $existing = FarmInvestment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id || (int) $existing->project_id !== $projectId || (int) $existing->shares_owned !== $requestedShares) {
                    throw new RuntimeException('Idempotency key was already used for another investment request.');
                }

                return $existing->fresh(['project.share']);
            }

            $project = FarmingProject::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
            $this->assertProjectEligible($project);
            if ($requestedShares <= 0) {
                throw new RuntimeException('Requested shares must be greater than zero.');
            }

            $share = FarmShare::query()->where('project_id', $project->id)->lockForUpdate()->firstOrFail();
            if ($requestedShares > (int) $share->shares_available) {
                throw new RuntimeException('Requested shares are not available.');
            }

            $compliance = $this->compliance->assertAllowed($user, 'AGRITECH_INVESTMENT', [
                'action' => 'INVEST',
                'project_id' => $project->id,
                'economic_type' => $project->economic_type,
                'jurisdiction' => $context['jurisdiction'] ?? null,
            ]);
            $risk = $this->security->evaluate('USER', $user->id, 'AGRITECH_INVESTMENT', [
                'project_id' => $project->id,
                'shares' => $requestedShares,
            ]);
            if (!in_array((string) $risk['decision'], ['ALLOW', 'ALLOW_WITH_MONITORING'], true)) {
                throw new RuntimeException('Investment is unavailable pending a security review.');
            }

            $principal = FinancialDecimal::mul((string) $requestedShares, (string) $share->price_per_share);
            $asset = strtoupper((string) ($project->currency ?: config('agriculture.financial.default_asset', 'USDT')));
            $pricingDecision = $this->pricingDecision($user, $project, $principal, $asset);
            $fee = $pricingDecision ? (string) $pricingDecision->fee_amount : '0';
            $total = FinancialDecimal::add($principal, $fee);

            $investment = FarmInvestment::query()->create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'shares_owned' => $requestedShares,
                'investment_amount' => $principal,
                'asset' => $asset,
                'status' => 'pending',
                'financial_status' => 'RESERVING',
                'idempotency_key' => $idempotencyKey,
                'pricing_decision_id' => $pricingDecision?->id,
                'compliance_snapshot' => $compliance,
                'locked_until' => $project->expected_harvest_date?->endOfDay(),
                'metadata' => ['security_decision_uuid' => $risk['decision_uuid'] ?? null],
            ]);

            $share->shares_reserved += $requestedShares;
            $share->shares_available -= $requestedShares;
            $share->save();

            try {
                $reservation = $this->reservations->reserveUserAccount(
                    $user->id,
                    'funding',
                    $asset,
                    $total,
                    'agritech_investment',
                    FarmInvestment::class,
                    (string) $investment->id,
                    'agri:reserve:' . $idempotencyKey,
                    ['project_id' => $project->id, 'shares' => $requestedShares],
                );
                $investment->reservation_id = $reservation->reservation_id;
                $investment->financial_status = 'RESERVED';
                $investment->save();

                $ledger = $this->settlements->agriInvestmentEscrow(
                    (string) $reservation->reservation_id,
                    $principal,
                    $fee,
                    'agri:investment:' . $idempotencyKey,
                    ['project_id' => $project->id, 'investment_id' => $investment->id, 'pricing_decision_id' => $pricingDecision?->id],
                );
                $this->confirmAllocation($investment, $share, $project, $ledger);
            } catch (\Throwable $exception) {
                $share->shares_reserved -= $requestedShares;
                $share->shares_available += $requestedShares;
                $share->save();
                $investment->forceFill(['status' => 'failed', 'financial_status' => 'FAILED'])->save();
                throw $exception;
            }

            return $investment->fresh(['project.share']);
        }, 3);
    }

    private function assertProjectEligible(FarmingProject $project): void
    {
        if (!in_array((string) $project->status, ['OPEN', 'open'], true)) {
            throw new RuntimeException('Project is not open for funding.');
        }
        if (!$project->public_funding_enabled || !(bool) config('agriculture.public_investment_enabled', false)) {
            throw new RuntimeException('Public AgriTech investment is disabled pending approval.');
        }
        if ((string) $project->verification_status !== 'VERIFIED') {
            throw new RuntimeException('Project verification is incomplete.');
        }
        if (in_array((string) $project->economic_type, self::FINANCIAL_TYPES, true) && (string) $project->legal_status !== 'APPROVED') {
            throw new RuntimeException('This investment product has not received legal approval.');
        }
        if ((string) $project->economic_type === 'TOKENIZED_INVESTMENT' && !(bool) config('agriculture.tokenized_investment_enabled', false)) {
            throw new RuntimeException('Tokenized AgriTech investment is disabled.');
        }
        if ($project->funding_deadline && now()->greaterThan($project->funding_deadline)) {
            throw new RuntimeException('Project funding deadline has passed.');
        }
    }

    private function pricingDecision(User $user, FarmingProject $project, string $principal, string $asset): ?PricingDecision
    {
        if (!$this->pricing->isEnforced('AGRITECH')) {
            return null;
        }

        return $this->pricing->quote($user, [
            'product' => 'AGRITECH',
            'operation' => 'INVESTMENT',
            'amount' => $principal,
            'asset' => $asset,
            'currency' => $asset,
            'country' => data_get($project->metadata, 'country'),
            'project_id' => $project->id,
            'economic_type' => $project->economic_type,
        ]);
    }

    private function confirmAllocation(FarmInvestment $investment, FarmShare $share, FarmingProject $project, LedgerTransaction $ledger): void
    {
        $share->shares_reserved -= (int) $investment->shares_owned;
        $share->shares_allocated += (int) $investment->shares_owned;
        $share->save();

        $investment->forceFill([
            'status' => 'locked',
            'financial_status' => 'SETTLED_IN_ESCROW',
            'ledger_transaction_id' => $ledger->id,
            'settled_at' => now(),
        ])->save();

        if ((int) $share->shares_available === 0 && in_array((string) $project->status, ['OPEN', 'open'], true)) {
            $project->status = 'FULLY_FUNDED';
            $project->save();
        }
    }
}
