# ExaEarn Listing P0 Withdrawal Activation Remediation

Date: 2026-09-01

## Previous behavior

`ListingLifecycleService::openWithdrawals()` enabled Listing and canonical blockchain withdrawal flags whenever the application was `LIVE`. Trading status was therefore incorrectly treated as sufficient evidence that ExaEarn could authorize, reserve, risk-check, sign, broadcast, track, and reconcile withdrawals.

## New readiness architecture

Withdrawal configuration, readiness, approval, enabled state, and runtime availability are now separate. `ListingWithdrawalReadinessService` evaluates every exact asset/network configuration and persists evidence in `listing_withdrawal_readiness_checks`. Any unknown, disconnected, failed, blocked, or unconfigured mandatory gate blocks activation.

Mandatory gates cover custody provider, production signer, hot wallet, wallet liquidity, bounded RPC observation, exact asset/network mapping, server-side destination validation, memo/tag policy, fees, limits, user security, account risk, withdrawal risk engine, canonical reservation, broadcast, confirmation tracking, reconciliation, and operational runbook readiness.

## Canonical controls

The implementation reuses `BlockchainProviderManager`, `DevelopmentSigningProvider` safety behavior, canonical custody wallets, `WithdrawalFeeService`, `WithdrawalRiskEngine`, `LedgerService`, `ReservationService`, `SettlementService`, `CustodyWithdrawalService`, confirmation records, and custody reconciliation. It creates no Listing wallet, signer, balance, risk engine, or broadcast path.

The canonical withdrawal service already provides stable request idempotency, row locking, one reservation, server-authoritative destination validation, memo handling, risk decisions, signing-request hashes, status-gated broadcasting, persisted transaction hashes, confirmation tracking, and canonical settlement. A repeated broadcast call after `BROADCASTED` is rejected by the state machine.

## Signer and private-key boundary

The repository contains only a development signing provider. It fails closed whenever production custody is enabled or the process is outside local/testing. Production activation additionally requires the configured signer identifier to be one of the approved MPC/HSM/multisig/external-signer providers and to pass health checks. Consequently, withdrawals cannot become ready in the current environment. The remediation does not add local production signing, persist signing secrets, or expose key material in evidence.

Existing legacy encrypted-key configuration is separate from the Listing activation path. No private key, seed phrase, MPC share, or signed payload is persisted in readiness evidence.

## Wallet, liquidity, fees, limits, and risk

An active HOT wallet for the exact asset/network is required, and its policy must identify the configured signer. Liquidity awareness, security, account controls, risk engine, ledger reservation, broadcast, tracking, reconciliation, and runbook evidence must be explicitly operational. Fee readiness requires canonical asset fee policy; limit readiness requires positive configured minimum and maximum values. Values are compared with `FinancialDecimal`.

## Configuration fingerprint and maker-checker

Readiness and approval are bound to a SHA-256 fingerprint of contract/native identity, network/chain, decimals, confirmation policy, memo policy, fees, limits, canonical asset/network policies, signer, and source-wallet identity/policy. Material changes invalidate approval.

Activation uses its own two-person workflow. A readiness check must pass before a request can be created. The approver cannot be the requester. Approval rechecks all gates, and scheduled execution rechecks them again before atomically consuming approval and enabling flags.

Admin endpoints:

- `GET /api/admin/v1/listing-center/applications/{reference}/withdrawal-readiness`
- `POST /api/admin/v1/listing-center/applications/{reference}/withdrawal-activation/request`
- `POST /api/admin/v1/listing-center/applications/{reference}/withdrawal-activation/approve`

They remain behind existing Listing admin RBAC and admin security middleware.

## Pause, resume, and runtime safety

`PAUSE_WITHDRAWALS` immediately disables the Listing asset, all Listing network configurations, and canonical blockchain assets without changing deposit flags. It invalidates pending/unconsumed approvals. `RESUME_WITHDRAWALS` requires current readiness and a fresh dual approval. Scheduled failures persist exact blocking gates and configuration fingerprint in the launch event and Listing audit log.

Global capability does not bypass per-user 2FA, account restrictions, compliance holds, destination checks, risk review, reservation, or normal withdrawal state transitions.

## External blockers

Production readiness remains blocked until an approved signer implementation, production custody/RPC credentials, signer-bound hot wallets, observable wallet liquidity/rebalancing, destination analytics where policy requires it, production risk dependencies, broadcast and tracking infrastructure, reconciliation evidence, alerting, and approved runbooks exist. No real customer funds are required or used by repository certification.

Deposit readiness was not modified. Document-malware remediation remains separate.
