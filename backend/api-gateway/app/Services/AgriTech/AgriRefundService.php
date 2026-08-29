<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\FarmInvestment;
use App\Models\FarmShare;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgriRefundService
{
    public function __construct(private readonly LedgerService $ledger, private readonly SettlementService $settlements)
    {
    }

    public function refund(User $actor, int $investmentId, string $reason): FarmInvestment
    {
        if ($actor->role !== 'admin') throw new RuntimeException('Authorized AgriTech operations access is required.');
        return DB::transaction(function () use ($actor, $investmentId, $reason): FarmInvestment {
            $investment = FarmInvestment::query()->with('project')->whereKey($investmentId)->lockForUpdate()->firstOrFail();
            if ($investment->financial_status === 'REFUNDED') return $investment;
            if ($investment->financial_status !== 'SETTLED_IN_ESCROW' || !in_array(strtoupper((string) $investment->project->status), ['REFUNDING', 'CANCELLED', 'FAILED'], true)) {
                throw new RuntimeException('Investment is not eligible for refund.');
            }
            $escrow = $this->ledger->getOrCreateAccount(null, 'agritech_investor_escrow', (string) $investment->asset);
            if (FinancialDecimal::compare((string) $escrow->balance, (string) $investment->investment_amount) < 0) {
                throw new RuntimeException('AgriTech escrow cannot cover this refund.');
            }
            $reference = 'agri:refund:investment:' . $investment->id;
            $transaction = $this->settlements->agriInvestmentRefund((int) $investment->user_id, (string) $investment->asset, (string) $investment->investment_amount, $reference, [
                'project_id' => $investment->project_id, 'investment_id' => $investment->id, 'reason' => $reason, 'actor_id' => $actor->id,
            ]);
            $investment->forceFill(['status' => 'refunded', 'financial_status' => 'REFUNDED', 'cancelled_at' => now(), 'metadata' => array_merge($investment->metadata ?? [], ['refund_ledger_transaction_id' => $transaction->id, 'refund_reason' => $reason])])->save();
            $share = FarmShare::query()->where('project_id', $investment->project_id)->lockForUpdate()->first();
            if ($share) {
                $share->shares_allocated -= (int) $investment->shares_owned;
                $share->shares_available += (int) $investment->shares_owned;
                $share->save();
            }
            return $investment;
        }, 3);
    }
}
