<?php

declare(strict_types=1);

namespace App\Services\AgriTech;

use App\Models\AgriDisbursement;
use App\Models\AgriProjectMilestone;
use App\Models\Farmer;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\LedgerService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AgriDisbursementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SettlementService $settlements,
    ) {
    }

    public function request(User $actor, int $milestoneId, int $farmerId, string $amount, string $idempotencyKey): AgriDisbursement
    {
        $this->assertAdmin($actor);
        return DB::transaction(function () use ($actor, $amount, $farmerId, $idempotencyKey, $milestoneId): AgriDisbursement {
            $existing = AgriDisbursement::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $milestone = AgriProjectMilestone::query()->whereKey($milestoneId)->lockForUpdate()->firstOrFail();
            if ($milestone->status !== 'APPROVED') {
                throw new RuntimeException('Milestone must be verified and approved before disbursement.');
            }
            $farmer = Farmer::query()->findOrFail($farmerId);
            if ($farmer->state !== 'APPROVED' || !$farmer->user_id) {
                throw new RuntimeException('An approved farmer payout account is required.');
            }
            $amount = FinancialDecimal::normalize($amount);
            if (FinancialDecimal::compare($amount, '0') <= 0 || FinancialDecimal::compare($amount, (string) $milestone->release_amount) > 0) {
                throw new RuntimeException('Disbursement amount exceeds the approved milestone amount.');
            }

            return AgriDisbursement::query()->create([
                'disbursement_uuid' => (string) Str::uuid(),
                'project_id' => $milestone->project_id,
                'milestone_id' => $milestone->id,
                'farmer_id' => $farmer->id,
                'amount' => $amount,
                'asset' => strtoupper((string) $milestone->asset),
                'status' => 'PENDING_APPROVAL',
                'idempotency_key' => $idempotencyKey,
                'requested_by' => $actor->id,
            ]);
        });
    }

    public function approve(User $actor, int $id): AgriDisbursement
    {
        $this->assertAdmin($actor);
        return DB::transaction(function () use ($actor, $id): AgriDisbursement {
            $row = AgriDisbursement::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($row->status === 'SETTLED') {
                return $row;
            }
            if ($row->status !== 'PENDING_APPROVAL') {
                throw new RuntimeException('Disbursement is not awaiting approval.');
            }
            $row->forceFill(['status' => 'AWAITING_CHECK', 'approved_by' => $actor->id])->save();
            return $row;
        });
    }

    public function checkAndSettle(User $actor, int $id): AgriDisbursement
    {
        $this->assertAdmin($actor);
        return DB::transaction(function () use ($actor, $id): AgriDisbursement {
            $row = AgriDisbursement::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($row->status === 'SETTLED') {
                return $row;
            }
            if ($row->status !== 'AWAITING_CHECK' || !$row->approved_by) {
                throw new RuntimeException('Disbursement is not ready for checker approval.');
            }
            if ((int) $row->approved_by === (int) $actor->id) {
                throw new RuntimeException('Maker-checker requires a different authorized approver.');
            }
            $farmer = Farmer::query()->findOrFail($row->farmer_id);
            $escrow = $this->ledger->getOrCreateAccount(null, 'agritech_investor_escrow', (string) $row->asset);
            if (FinancialDecimal::compare((string) $escrow->balance, (string) $row->amount) < 0) {
                throw new RuntimeException('AgriTech escrow has insufficient settled funds.');
            }
            $ledger = $this->settlements->agriEscrowDisbursement(
                (int) $farmer->user_id,
                (string) $row->asset,
                (string) $row->amount,
                'agri:disbursement:' . $row->idempotency_key,
                ['project_id' => $row->project_id, 'milestone_id' => $row->milestone_id, 'disbursement_id' => $row->id],
            );
            $row->forceFill(['status' => 'SETTLED', 'checked_by' => $actor->id, 'ledger_transaction_id' => $ledger->id, 'settled_at' => now()])->save();
            AgriProjectMilestone::query()->whereKey($row->milestone_id)->update(['status' => 'DISBURSED']);
            return $row;
        }, 3);
    }

    private function assertAdmin(User $actor): void
    {
        if ($actor->role !== 'admin') {
            throw new RuntimeException('Authorized AgriTech operations access is required.');
        }
    }
}
