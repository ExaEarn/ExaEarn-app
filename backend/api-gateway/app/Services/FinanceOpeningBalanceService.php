<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\FinanceOpeningBalanceImport;
use Illuminate\Support\Str;
use RuntimeException;

class FinanceOpeningBalanceService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinanceAccountingService $accounting,
    ) {
    }

    public function request(Admin $admin, array $payload): FinanceOpeningBalanceImport
    {
        return FinanceOpeningBalanceImport::query()->create([
            'import_uuid' => (string) Str::uuid(),
            'requested_by_admin_id' => $admin->id,
            'asset' => strtoupper((string) $payload['asset']),
            'amount' => FinancialDecimal::normalize((string) $payload['amount']),
            'debit_account_type' => (string) $payload['debit_account_type'],
            'credit_account_type' => (string) $payload['credit_account_type'],
            'ownership_class' => strtoupper((string) $payload['ownership_class']),
            'status' => 'PENDING_APPROVAL',
            'evidence' => $payload['evidence'],
            'reason' => (string) $payload['reason'],
        ]);
    }

    public function approve(Admin $admin, FinanceOpeningBalanceImport $import): FinanceOpeningBalanceImport
    {
        if ((int) $import->requested_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Opening balance approval requires maker-checker control.');
        }
        if ($import->status !== 'PENDING_APPROVAL') {
            throw new RuntimeException('Only pending opening balances can be approved.');
        }
        $debit = $this->ledger->getOrCreateAccount(null, (string) $import->debit_account_type, (string) $import->asset);
        $credit = $this->ledger->getOrCreateAccount(null, (string) $import->credit_account_type, (string) $import->asset);
        $reference = 'FIN-OPEN-'.$import->import_uuid;
        $transaction = $this->ledger->postDoubleEntry($reference, 'Approved opening balance import', [
            ['account_id' => $debit->id, 'amount' => FinancialDecimal::sub('0', (string) $import->amount), 'asset' => $import->asset],
            ['account_id' => $credit->id, 'amount' => (string) $import->amount, 'asset' => $import->asset],
        ], 'finance_opening_balance', [
            'source_service' => 'finance',
            'opening_balance_import_uuid' => $import->import_uuid,
            'evidence' => $import->evidence,
            'requested_by_admin_id' => $import->requested_by_admin_id,
            'approved_by_admin_id' => $admin->id,
        ]);
        $this->accounting->recordLedgerEvent($transaction, 'OPENING_BALANCE_MIGRATION', ['opening_balance_import_uuid' => $import->import_uuid]);
        $import->forceFill([
            'approved_by_admin_id' => $admin->id,
            'status' => 'POSTED',
            'ledger_reference' => $reference,
            'approved_at' => now(),
        ])->save();

        return $import->fresh();
    }
}
