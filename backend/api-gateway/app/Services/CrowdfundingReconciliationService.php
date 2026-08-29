<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrowdfundingCampaign;
use App\Models\CrowdfundingPledge;
use App\Models\CrowdfundingReconciliationIncident;
use App\Models\LedgerEntry;

class CrowdfundingReconciliationService
{
    public function run(?CrowdfundingCampaign $campaign = null): array
    {
        $findings = [];
        $campaigns = CrowdfundingCampaign::query()->when($campaign, fn ($q) => $q->whereKey($campaign->id))->get();

        foreach ($campaigns as $row) {
            $pledgeTotal = CrowdfundingPledge::query()
                ->where('campaign_id', $row->id)
                ->whereIn('status', ['HELD_IN_ESCROW', 'RELEASED', 'REFUND_PENDING'])
                ->selectRaw('COALESCE(SUM(amount), 0) as total')
                ->value('total') ?: '0';

            $terminalHistorical = in_array($row->status, ['REFUNDED', 'CANCELLED', 'FAILED'], true);
            if (!$terminalHistorical && FinancialDecimal::compare(FinancialDecimal::normalize((string) $pledgeTotal), (string) $row->raised_amount) !== 0) {
                $findings[] = $this->finding($row, 'CAMPAIGN_TOTAL_MISMATCH', ['pledges' => (string) $pledgeTotal, 'raised_amount' => (string) $row->raised_amount]);
            }

            CrowdfundingPledge::query()->where('campaign_id', $row->id)->where('status', 'HELD_IN_ESCROW')->chunkById(100, function ($pledges) use (&$findings, $row): void {
                foreach ($pledges as $pledge) {
                    $ledger = LedgerEntry::query()->where('reference', $pledge->ledger_reference)->count();
                    if (!$pledge->reservation_id || !$pledge->ledger_reference || $ledger < 2) {
                        $findings[] = $this->finding($row, 'PLEDGE_WITHOUT_ESCROW_LEDGER', ['pledge_id' => $pledge->id]);
                    }
                }
            });
        }

        foreach ($findings as $finding) {
            CrowdfundingReconciliationIncident::query()->firstOrCreate([
                'campaign_id' => $finding['campaign_id'],
                'incident_type' => $finding['type'],
                'status' => 'OPEN',
            ], [
                'severity' => 'HIGH',
                'evidence' => $finding['evidence'],
            ]);
        }

        return ['status' => $findings === [] ? 'PASS' : 'BREAKS_FOUND', 'findings' => $findings, 'checked_at' => now()->toISOString()];
    }

    private function finding(CrowdfundingCampaign $campaign, string $type, array $evidence): array
    {
        return ['campaign_id' => $campaign->id, 'type' => $type, 'evidence' => $evidence];
    }
}
