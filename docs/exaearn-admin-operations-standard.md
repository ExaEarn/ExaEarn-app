# ExaEarn Admin Operations Standard

## Principles

Admin screens must display one of two things:

- authoritative backend data from real controllers/services
- an explicit unavailable or not-ready state

They must not display fabricated records, fake operational metrics, or simulated success for financial/admin actions.

## Financial Guardrails

- Admin controllers must not directly mutate wallet or account balances.
- Economic actions must flow through LedgerService, ReservationService, SettlementService, or the product service that already uses those canonical paths.
- Reconciliation findings must be reviewable and auditable; admin tools must not silently repair financial differences.
- High-impact configuration changes should use existing RBAC, audit logging, and maker-checker where available.

## UI Fallback Policy

If a module API fails, the admin UI shows:

- no placeholder records
- no fallback actions
- an unavailable status
- a clear message that authoritative backend data did not load

Operator actions are only shown when a real backend response provides them or the module has an explicitly implemented action path.
