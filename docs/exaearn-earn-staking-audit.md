# ExaEarn Earn / Staking Audit

## Maturity

Current level: Level 3 software ready for gated release; Level 4 operational readiness is blocked by real provider and operations setup.

## What Exists

- Web and mobile staking screens.
- Public v1 staking routes and developer API staking routes.
- Admin staking operations routes for assets, products, validators, approvals, provider health, wallets, batches, reward batches, reconciliation reports, and audit logs.
- `StakingService`, provider registry, network-specific native staking providers, secure signer, staking ledger service, and staking jobs.
- Migrations for staking assets, products, positions, transactions, approvals, batches, wallets, reward allocations, and reconciliation.
- Tests cover XRP removal, legacy route removal, fail-closed provider readiness, admin activation approvals, reward claims, delegation activation, unstaking, secure signing, and failure reversal.

## Key Strengths

- User principal reservation is ledger-backed.
- Product creation is gated by provider health/readiness.
- Native reward claiming fails closed without verified allocation.
- Mainnet activation requires administrative control.

## Remaining Blockers

- Real secure signer configuration and chain/provider credentials are external.
- Mainnet validator/wallet fee funding and reward allocation pipelines require operational proof.
- Some arithmetic still uses local helpers with float fallback in older patterns; financial paths should converge fully on `FinancialDecimal`.
- User-facing labels need to distinguish testnet, internal, limited release, and production networks.

## Required Next Work

1. Complete provider-by-provider mainnet readiness runbooks.
2. Require funded wallet and signer checks before enabling new positions.
3. Run recurring staking reconciliation in production mode.
4. Connect staking notifications to every lifecycle event.

