<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\AgriHarvestSettlement;
use App\Models\AgriInvestorAllocation;
use App\Models\AgriReconciliationFinding;
use App\Models\FarmInvestment;
use App\Models\FarmingProject;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgriTechReconciliationService
{
    public function reconcile(?int $projectId = null): array
    {
        $findings = [];
        FarmingProject::query()->when($projectId, fn ($query) => $query->whereKey($projectId))->with('share')->each(function (FarmingProject $project) use (&$findings): void {
            $share = $project->share;
            if ($share && ((int) $share->shares_available + (int) $share->shares_reserved + (int) $share->shares_allocated !== (int) $share->total_shares)) {
                $findings[] = $this->finding($project->id, 'SHARE_CAPACITY_MISMATCH', [
                    'total' => (int) $share->total_shares,
                ], [
                    'available' => (int) $share->shares_available,
                    'reserved' => (int) $share->shares_reserved,
                    'allocated' => (int) $share->shares_allocated,
                ]);
            }

            $orphaned = FarmInvestment::query()->where('project_id', $project->id)
                ->where('financial_status', 'SETTLED_IN_ESCROW')
                ->whereNull('ledger_transaction_id')->count();
            if ($orphaned > 0) {
                $findings[] = $this->finding($project->id, 'INVESTMENT_LEDGER_MISMATCH', ['orphaned' => 0], ['orphaned' => $orphaned]);
            }

            AgriHarvestSettlement::query()->where('project_id', $project->id)->get()->each(function (AgriHarvestSettlement $settlement) use (&$findings, $project): void {
                $paid = (string) AgriInvestorAllocation::query()->where('harvest_settlement_id', $settlement->id)->sum('net_amount');
                if ($settlement->status === 'SETTLED' && FinancialDecimal::compare($paid, (string) $settlement->net_distributable) !== 0) {
                    $findings[] = $this->finding($project->id, 'HARVEST_PAYOUT_MISMATCH', ['net_distributable' => (string) $settlement->net_distributable], ['allocated' => $paid]);
                }
            });
        });

        return ['status' => $findings === [] ? 'PASS' : 'FAIL', 'findings' => $findings, 'checked_at' => now()->toIso8601String()];
    }

    private function finding(int $projectId, string $type, array $expected, array $actual): array
    {
        $fingerprint = hash('sha256', $projectId . '|' . $type . '|' . json_encode($expected) . '|' . json_encode($actual));
        $row = AgriReconciliationFinding::query()->firstOrCreate([
            'project_id' => $projectId,
            'finding_type' => $type,
            'status' => 'OPEN',
        ], [
            'finding_uuid' => (string) Str::uuid(),
            'severity' => 'CRITICAL',
            'description' => str_replace('_', ' ', $type),
            'expected' => $expected,
            'actual' => $actual,
            'metadata' => ['fingerprint' => $fingerprint],
        ]);

        return $row->toArray();
    }
}
