# ExaEarn Phase 15D Preimplementation Audit

## Existing And Reused

- Phase 15B institutional accounts, subaccounts, RBAC, audit and realtime replay.
- Phase 15C market-maker profiles and explicit market-maker capital model.
- Phase 14 developer API key infrastructure for future OTC external API exposure.
- Phase 1 canonical ledger and `ReservationService`.
- `BalanceProjectionService` for available balance checks.
- Admin security, audit middleware and role permissions.

## Partial

- External venue/provider OTC settlement is modeled and capability-gated, but no live production external OTC adapter or settlement credentials are configured.
- OTC maker-checker is represented through institutional permissions and admin controls; large-trade desk approval workflows can be expanded with additional approval tables.

## Missing Before Phase 15D

- OTC RFQ state machine.
- OTC market configuration.
- Explicit OTC liquidity provider registry.
- Firm quote lifecycle and expiry.
- Internal MM block-trade settlement.
- OTC-specific accounting/reconciliation/audit records.

## Dangerous Paths Avoided

- No new wallet system.
- No direct balance mutation.
- No fake public market data or Spot trade tape updates.
- No automatic conversion of every market maker into an OTC LP.
