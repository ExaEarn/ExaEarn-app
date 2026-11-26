# ExaEarn Listing P0 Deposit Activation Remediation

Date: 2026-09-01

## Previous behavior

`ListingLifecycleService::openDeposits()` enabled the Listing network configuration, canonical `BlockchainAsset`, and Listing asset after contract validation alone. It did not require evidence that custody could observe, attribute, confirm, credit, and reconcile deposits for the exact asset/network configuration.

## Readiness architecture

Deposit readiness is now separate from contract validation and from the enabled flag. `ListingDepositReadinessService` evaluates every configured network and persists an evidence snapshot in `listing_deposit_readiness_checks`. Every mandatory gate must be `PASS`; unknown and unavailable states fail closed.

Required gates are custody provider, bounded RPC observation, exact asset/network/contract/decimal mapping, address generation, memo/tag policy, deposit monitor, confirmation/finality policy, canonical ledger posting, custody reconciliation, risk controls, security controls, and operational runbook readiness.

The service calls the canonical blockchain provider for current health and a latest-block observation. Exact mapping is checked against `BlockchainNetwork`, `BlockchainAsset`, and `ListingAssetNetworkConfiguration`. Address readiness requires an active canonical deposit/hot wallet. The remaining operational evidence is read from the network configuration's `deposit_readiness` evidence map; missing evidence is `NOT_CONFIGURED`, never `PASS`. Production custody must be explicitly enabled. Development provider behavior cannot authorize production because it reports offline outside local/testing.

## Configuration binding

Each check and approval is bound to a SHA-256 fingerprint containing network/chain identity, canonical asset identity and metadata, contract, decimals, token standard, confirmation/finality values, memo policy, minimum deposit, and network policy. Activation recomputes the fingerprint and all live gates. A changed configuration invalidates the approval.

## Maker-checker

Deposit activation has its own request and approval records. Readiness must pass before a request can be created. The approver must differ from the requester. A new request invalidates older pending/approved requests. Approval re-evaluates readiness; scheduled execution re-evaluates it again and atomically consumes the approval.

Admin endpoints:

- `GET /api/admin/v1/listing-center/applications/{reference}/deposit-readiness`
- `POST /api/admin/v1/listing-center/applications/{reference}/deposit-activation/request`
- `POST /api/admin/v1/listing-center/applications/{reference}/deposit-activation/approve`

## Scheduled activation and idempotency

`DEPOSIT_OPEN` no longer enables deposits from contract validation. It requires a current passing snapshot and approved matching fingerprint. Blocking gates and the fingerprint are persisted on the launch event. Executed events remain idempotent through the existing event status and idempotency key. Database flags and approval consumption are committed in one transaction; no external provider mutation occurs during activation.

## Pause and resume

`PAUSE_DEPOSITS` immediately disables the Listing asset, every Listing network configuration, and canonical blockchain assets, and invalidates unconsumed requests/approvals. `RESUME_DEPOSITS` requires a new passing evaluation and fresh dual approval. Both actions use existing admin RBAC and Listing audit logs.

## Canonical infrastructure and boundaries

The implementation reuses ExaEarn's blockchain provider manager, network/asset registry records, custody wallets, deposit monitoring/settlement pipeline, canonical ledger, custody reconciliation tables, admin RBAC, and Listing audit log. It creates no Listing wallet, balance, ledger, scanner, or provider.

The existing canonical deposit pipeline already identifies events by network, transaction hash, and event identifier, locks duplicate detection, waits for configured confirmations, detects changed block hashes as reorg pending, and credits via `SettlementService`. Phase 9 custody tests remain the executable pipeline and duplicate-credit evidence. Real provider credentials, production RPCs, active custody wallets/address derivation, monitor workers/checkpoints, reconciled production observations, and approved operational runbooks remain deployment evidence. Until supplied, the relevant gates stay `NOT_CONNECTED` or `NOT_CONFIGURED` and deposits stay disabled.

## Validation

- Listing/deposit-focused regression: 12 passed, 207 assertions.
- Contract validation alone: blocked.
- Missing custody/operational evidence: blocked.
- Same requester and approver: blocked.
- Scheduled activation: re-evaluates readiness and fingerprint before enabling.
- Real customer funds: not required and not used.

Withdrawal activation was intentionally not changed by this remediation.
