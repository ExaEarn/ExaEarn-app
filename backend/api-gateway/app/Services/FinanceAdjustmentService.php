<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\FinanceAdjustmentRequest;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceAdjustmentService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinanceAccountingService $accounting,
        private readonly AdminAuditService $audit,
    ) {
    }

    public function request(Admin $admin, array $payload): FinanceAdjustmentRequest
    {
        $adjustment = FinanceAdjustmentRequest::query()->create([
            'adjustment_uuid' => (string) Str::uuid(),
            'requested_by_admin_id' => $admin->id,
            'asset' => strtoupper((string) $payload['asset']),
            'amount' => FinancialDecimal::normalize((string) $payload['amount']),
            'debit_account_type' => (string) $payload['debit_account_type'],
            'credit_account_type' => (string) $payload['credit_account_type'],
            'reason_code' => strtoupper((string) $payload['reason_code']),
            'reason' => (string) $payload['reason'],
            'status' => 'PENDING_APPROVAL',
            'metadata' => $payload['metadata'] ?? [],
        ]);
        $this->audit->log($admin, 'finance.adjustment.requested', ['adjustment_uuid' => $adjustment->adjustment_uuid]);

        return $adjustment->fresh();
    }

    public function approve(Admin $admin, FinanceAdjustmentRequest $adjustment, string $approvalReason): FinanceAdjustmentRequest
    {
        if ((int) $adjustment->requested_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Financial adjustments require maker-checker approval.');
        }
        if ($adjustment->status !== 'PENDING_APPROVAL') {
            throw new RuntimeException('Only pending financial adjustments can be approved.');
        }

        $asset = (string) $adjustment->asset;
        $amount = (string) $adjustment->amount;
        $debit = $this->ledger->getOrCreateAccount(null, (string) $adjustment->debit_account_type, $asset);
        $credit = $this->ledger->getOrCreateAccount(null, (string) $adjustment->credit_account_type, $asset);
        $reference = 'FIN-ADJ-'.$adjustment->adjustment_uuid;
        $transaction = $this->ledger->postDoubleEntry($reference, 'Approved financial adjustment', [
            ['account_id' => $debit->id, 'amount' => FinancialDecimal::sub('0', $amount), 'asset' => $asset],
            ['account_id' => $credit->id, 'amount' => $amount, 'asset' => $asset],
        ], 'finance_adjustment', ['reason_code' => $adjustment->reason_code, 'approval_reason' => $approvalReason, 'source_service' => 'finance']);

        $this->accounting->recordLedgerEvent($transaction, 'ADMIN_ADJUSTMENT', ['adjustment_uuid' => $adjustment->adjustment_uuid]);
        $adjustment->forceFill([
            'approved_by_admin_id' => $admin->id,
            'status' => 'POSTED',
            'ledger_reference' => $reference,
            'approved_at' => now(),
            'metadata' => array_merge($adjustment->metadata ?? [], ['approval_reason' => $approvalReason]),
        ])->save();
        $this->audit->log($admin, 'finance.adjustment.approved', ['adjustment_uuid' => $adjustment->adjustment_uuid, 'ledger_reference' => $reference]);

        return $adjustment->fresh();
    }
}
