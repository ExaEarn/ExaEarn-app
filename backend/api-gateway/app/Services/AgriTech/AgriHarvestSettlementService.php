<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\AgriHarvestSettlement;
use App\Models\AgriInvestorAllocation;
use App\Models\FarmInvestment;
use App\Models\FarmingProject;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\PricingPolicyEngine;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AgriHarvestSettlementService
{
    public function __construct(
        private readonly SettlementService $settlements,
        private readonly PricingPolicyEngine $pricing,
    ) {
    }

    public function settle(User $actor, int $projectId, array $payload): AgriHarvestSettlement
    {
        if ($actor->role !== 'admin') {
            throw new RuntimeException('Only authorized operations staff may settle verified harvest revenue.');
        }

        return DB::transaction(function () use ($actor, $payload, $projectId): AgriHarvestSettlement {
            $idempotencyKey = (string) $payload['idempotency_key'];
            $existing = AgriHarvestSettlement::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->fresh('allocations');
            }

            $project = FarmingProject::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();
            if (!in_array((string) $project->status, ['HARVEST_PENDING', 'HARVESTED', 'SETTLEMENT_PENDING', 'active'], true)) {
                throw new RuntimeException('Project is not eligible for harvest settlement.');
            }
            if ((string) $project->verification_status !== 'VERIFIED') {
                throw new RuntimeException('Project verification is incomplete.');
            }

            $evidenceExists = DB::table('agri_project_evidence')
                ->where('project_id', $project->id)
                ->where('id', (int) $payload['evidence_id'])
                ->where('evidence_type', 'HARVEST_REVENUE')
                ->where('status', 'APPROVED')
                ->exists();
            if (!$evidenceExists) {
                throw new RuntimeException('Approved harvest revenue evidence is required.');
            }

            $gross = FinancialDecimal::normalize((string) $payload['gross_revenue']);
            $costs = FinancialDecimal::normalize((string) ($payload['verified_costs'] ?? '0'));
            if (FinancialDecimal::compare($gross, '0') <= 0 || FinancialDecimal::compare($costs, $gross) > 0) {
                throw new RuntimeException('Verified harvest revenue values are invalid.');
            }
            $asset = strtoupper((string) ($payload['asset'] ?? $project->currency ?? 'USDT'));
            $fee = '0';
            if ($this->pricing->isEnforced('AGRITECH')) {
                $fee = (string) $this->pricing->quote($actor, [
                    'product' => 'AGRITECH',
                    'operation' => 'HARVEST_SETTLEMENT',
                    'amount' => $gross,
                    'asset' => $asset,
                    'currency' => $asset,
                    'project_id' => $project->id,
                ])->fee_amount;
            }
            $net = FinancialDecimal::sub(FinancialDecimal::sub($gross, $costs), $fee);
            if (FinancialDecimal::compare($net, '0') <= 0) {
                throw new RuntimeException('Verified distributable revenue must be greater than zero.');
            }

            $settlement = AgriHarvestSettlement::query()->create([
                'settlement_uuid' => (string) Str::uuid(),
                'project_id' => $project->id,
                'period_key' => (string) $payload['period_key'],
                'status' => 'VERIFIED',
                'revenue_source_type' => strtoupper((string) $payload['revenue_source_type']),
                'revenue_reference' => (string) $payload['revenue_reference'],
                'gross_revenue' => $gross,
                'verified_costs' => $costs,
                'platform_fee' => $fee,
                'net_distributable' => $net,
                'asset' => $asset,
                'idempotency_key' => $idempotencyKey,
                'verified_by' => $actor->id,
                'approved_by' => $actor->id,
                'verified_at' => now(),
                'metadata' => ['evidence_id' => (int) $payload['evidence_id']],
            ]);

            $revenueLedger = $this->settlements->agriVerifiedRevenue(
                $asset,
                $gross,
                'agri:harvest-revenue:' . $idempotencyKey,
                ['project_id' => $project->id, 'settlement_id' => $settlement->id, 'revenue_reference' => $settlement->revenue_reference],
            );
            $settlement->revenue_ledger_transaction_id = $revenueLedger->id;
            $settlement->save();
            $this->settlements->agriHarvestDeductions(
                $asset,
                $costs,
                $fee,
                'agri:harvest-deductions:' . $idempotencyKey,
                ['project_id' => $project->id, 'settlement_id' => $settlement->id],
            );

            $investments = FarmInvestment::query()
                ->where('project_id', $project->id)
                ->where('financial_status', 'SETTLED_IN_ESCROW')
                ->where('shares_owned', '>', 0)
                ->lockForUpdate()
                ->get();
            $totalShares = (string) $investments->sum('shares_owned');
            if (FinancialDecimal::compare($totalShares, '0') <= 0) {
                throw new RuntimeException('No financially settled investor shares exist for this project.');
            }

            $allocated = '0';
            foreach ($investments as $index => $investment) {
                $amount = $index === $investments->count() - 1
                    ? FinancialDecimal::sub($net, $allocated)
                    : FinancialDecimal::mul($net, FinancialDecimal::div((string) $investment->shares_owned, $totalShares));
                $allocated = FinancialDecimal::add($allocated, $amount);
                $allocationKey = $idempotencyKey . ':investor:' . $investment->id;
                $allocation = AgriInvestorAllocation::query()->create([
                    'harvest_settlement_id' => $settlement->id,
                    'investment_id' => $investment->id,
                    'user_id' => $investment->user_id,
                    'gross_amount' => $amount,
                    'fee_amount' => '0',
                    'net_amount' => $amount,
                    'asset' => $asset,
                    'status' => 'PAYABLE',
                    'allocation_version' => 'agri-v1',
                    'idempotency_key' => $allocationKey,
                    'metadata' => ['basis' => 'SETTLED_SHARE_OWNERSHIP', 'shares' => $investment->shares_owned, 'total_shares' => $totalShares],
                ]);
                $ledger = $this->settlements->agriInvestorPayout(
                    (int) $investment->user_id,
                    $asset,
                    $amount,
                    'agri:investor-payout:' . $allocationKey,
                    ['project_id' => $project->id, 'settlement_id' => $settlement->id, 'investment_id' => $investment->id],
                );
                $allocation->forceFill(['status' => 'PAID', 'ledger_transaction_id' => $ledger->id, 'paid_at' => now()])->save();
            }

            $settlement->forceFill(['status' => 'SETTLED', 'settled_at' => now()])->save();
            $project->status = 'COMPLETED';
            $project->save();

            return $settlement->fresh('allocations');
        }, 3);
    }
}
