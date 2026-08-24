# ExaEarn Phase 9 Pre-Implementation Audit

Date: 2026-08-22

## Scope

Audited the existing ExaEarn backend custody-adjacent implementation before adding Phase 9:

- Wallet and legacy wallet balances: `WalletService`, `Wallet`, `WalletBalance`, `Balance`
- Canonical accounting: `LedgerService`, `SettlementService`, `ReservationService`, `BalanceProjectionService`
- Deposit and withdrawal flows: `DepositService`, `WithdrawalService`, `WithdrawalEngineService`, `FiatWithdrawalIntentService`
- Blockchain bridge: `BlockchainService` and `backend/services/blockchain-service`
- Treasury and Phase 8 liquidity: `TreasuryService`, `CryptoTreasuryService`, `TreasuryRouterService`, Phase 8 liquidity services
- Routes: `wallet`, `withdrawals`, `webhooks`, `admin/treasury`

## Findings

| Component | Classification | Notes |
| --- | --- | --- |
| `LedgerService` | KEEP / HARDEN | Canonical double-entry foundation exists and is reused. Rollbacks already create reversing entries instead of deleting history. |
| `ReservationService` | KEEP | Used for Phase 9 withdrawal reservation. |
| `SettlementService` | HARDEN | Added custody-specific withdrawal settlement to separate recipient amount, network fee, and platform fee. |
| `WalletService` legacy balance mutation | CONSOLIDATE | Still contains direct legacy balance mutations for compatibility. Phase 9 uses canonical reservations and ledger. |
| `DepositService` | HARDEN | Existing deposit service records application deposits; Phase 9 adds blockchain-evidence-driven detection and exactly-once crediting. |
| `WithdrawalEngineService` | CONSOLIDATE | Existing flow validates and locks legacy wallet balances. Phase 9 introduces durable custody withdrawals with canonical reservations. |
| `BlockchainService` | KEEP / HARDEN | Existing HTTP bridge to Node blockchain service remains useful as an external provider integration point. |
| `config/wallet.php` | KEEP | Existing asset/network list informed Phase 9 registry. |
| `config/crypto.php` encrypted private-key entries | HARDEN | Production signing must move behind MPC/HSM/external signer abstraction. No raw keys are stored by Phase 9. |
| Node chain services | HARDEN | Useful adapters exist, but some services still reference local hot-wallet secrets. Phase 9 treats them as provider layer, not final production key custody. |
| Phase 8 treasury/withdrawal reserve services | KEEP | Phase 9 readiness/admin surfaces include reserve integration points. |

## Risks Found

- Legacy services still have compatibility balance writes.
- Production RPC/indexer providers are not configured in this repository.
- Production MPC/HSM/multisig signing infrastructure is not configured.
- Hot wallets, network-fee reserves, and withdrawal reserves require real operational funding before production activation.
- Existing Node blockchain service must be hardened before it can hold production signing authority.

## Outcome

Phase 9 was implemented as an additive production custody layer that reuses canonical ledger/reservation infrastructure and does not fabricate deposits, transaction hashes, private keys, or production readiness.
