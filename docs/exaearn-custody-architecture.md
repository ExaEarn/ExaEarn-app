# ExaEarn Custody Architecture

Phase 9 introduces the backend custody control layer for multi-chain deposits, withdrawals, treasury custody state, and reconciliation.

## Core Flow

```text
Blockchain/indexer evidence
    -> DepositMonitoringService
    -> confirmation/reorg checks
    -> SettlementService deposit credit
    -> canonical LedgerService
    -> user funding balance projection
```

```text
Withdrawal request
    -> CustodyWithdrawalService
    -> chain-aware validation
    -> WithdrawalRiskEngine
    -> ReservationService
    -> transaction construction
    -> SigningProviderInterface
    -> BlockchainProviderInterface broadcast
    -> finality monitoring
    -> SettlementService custody withdrawal
    -> canonical LedgerService
```

## New Backend Pieces

- `config/custody.php`
- `BlockchainNetworkInterface`
- `BlockchainProviderInterface`
- `SigningProviderInterface`
- `CustodyRegistryService`
- `CustodyAddressService`
- `DepositMonitoringService`
- `CustodyWithdrawalService`
- `WithdrawalRiskEngine`
- `WithdrawalFeeService`
- `BlockchainNonceService`
- `BitcoinUtxoService`
- `DepositSweepService`
- `NetworkFeeManagementService`
- `BlockchainNetworkHealthService`
- `CustodyReconciliationService`
- `CustodyOperationalReadinessService`
- `Admin\CustodyOperationsController`

## Storage

New read/control models are stored in Phase 9 custody tables:

- `blockchain_networks`
- `blockchain_assets`
- `blockchain_providers`
- `custody_wallets`
- `custody_addresses`
- `custody_address_assignments`
- `custody_deposits`
- `custody_withdrawals`
- `custody_signing_requests`
- `custody_broadcast_attempts`
- `custody_transaction_confirmations`
- `bitcoin_utxos`
- `blockchain_nonce_states`
- `custody_reconciliation_runs`
- `custody_daily_snapshots`

## Production Boundary

The included development provider/signer only works in `local` and `testing`. When `CUSTODY_PRODUCTION_ENABLED=true`, production signing must use one of:

- MPC
- HSM
- Multisig
- External signer

The backend intentionally reports operational setup as required when production RPC, signer, hot-wallet funding, or fee reserves are missing.
