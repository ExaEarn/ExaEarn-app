<?php

declare(strict_types=1);

namespace App\Services\Custody;

use Illuminate\Support\Facades\DB;

class CustodyJobOrchestratorService
{
    public function pendingWork(): array
    {
        return [
            'ScanBlockchainDeposits' => $this->countNetworks(),
            'ProcessDetectedDeposit' => $this->countDeposits(['DETECTED']),
            'UpdateDepositConfirmations' => $this->countDeposits(['DETECTED', 'CONFIRMING']),
            'CreditConfirmedDeposit' => $this->countDeposits(['CONFIRMED']),
            'DetectChainReorganizations' => $this->countDeposits(['REORG_PENDING']),
            'ProcessWithdrawalRequest' => $this->countWithdrawals(['REQUESTED', 'VALIDATING']),
            'RunWithdrawalRiskCheck' => $this->countWithdrawals(['REQUESTED']),
            'BuildWithdrawalTransaction' => $this->countWithdrawals(['BALANCE_RESERVED', 'APPROVED', 'QUEUED']),
            'RequestWithdrawalSignature' => $this->countWithdrawals(['BUILDING', 'SIGNING']),
            'BroadcastWithdrawal' => $this->countWithdrawals(['SIGNED', 'BROADCASTING']),
            'MonitorWithdrawal' => $this->countWithdrawals(['BROADCASTED', 'CONFIRMING']),
            'FinalizeWithdrawal' => $this->countWithdrawals(['CONFIRMING']),
            'RecoverUnknownBroadcast' => $this->countWithdrawals(['BROADCASTING']),
            'EvaluateDepositSweeps' => DB::table('custody_deposits')->where('status', 'CREDITED')->count(),
            'ExecuteDepositSweep' => DB::table('custody_sweeps')->where('status', 'PLANNED')->count(),
            'EvaluateHotWalletRebalance' => DB::table('custody_wallets')->where('classification', 'HOT')->count(),
            'ReplenishNetworkFeeWallet' => DB::table('custody_network_fee_reserves')->whereIn('status', ['LOW', 'CRITICAL'])->count(),
            'RefreshBlockchainNetworkHealth' => $this->countNetworks(),
            'ReconcileCustody' => $this->countNetworks(),
            'GenerateCustodySnapshot' => $this->countNetworks(),
            'ConsolidateBitcoinUtxos' => DB::table('bitcoin_utxos')->where('spend_status', 'UNSPENT')->count(),
        ];
    }

    private function countDeposits(array $statuses): int
    {
        return DB::table('custody_deposits')->whereIn('status', $statuses)->count();
    }

    private function countWithdrawals(array $statuses): int
    {
        return DB::table('custody_withdrawals')->whereIn('status', $statuses)->count();
    }

    private function countNetworks(): int
    {
        return DB::table('blockchain_networks')->count();
    }
}
