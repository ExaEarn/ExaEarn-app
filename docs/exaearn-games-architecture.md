# EXA Flight Architecture

## Product Control

EXA Flight now has an explicit product classification control plane through `FlightGamePolicyService`.

Supported classifications:

- `ENTERTAINMENT_ONLY`
- `FREE_TO_PLAY`
- `REWARD_BASED`
- `PROMOTIONAL`
- `SKILL_BASED`
- `REAL_MONEY_GAMING`
- `REGULATED_GAMBLING`

Default classification is `REGULATED_GAMBLING`, with `game_mode=sandbox`. Public real-money mode is disabled unless both `public_real_money_enabled` and `legal_real_money_approved` are true and the mode is `real` or `hybrid`.

## Runtime Flow

1. Client requests game state.
2. `FlightGameService` creates or returns the active round.
3. Formal `round_state` controls whether entry, cashout or settlement is allowed.
4. User entry calls `FlightGamePolicyService`.
5. Demo entries are accepted without ledger mutation.
6. Real-money entries require compliance, security, KYC, jurisdiction, responsible-gaming and treasury-risk checks.
7. Real-money entries create a canonical reservation.
8. Entry locking consumes the reservation and moves funds from `funding` to `game_locked`.
9. Cashout/loss/cancel settlement posts canonical ledger transactions.
10. Finance accounting records game ledger events.
11. Realtime publishes game state, but the database and ledger remain authoritative.

## Admin Surface

Admin routes expose summary, settings, tick, control actions, and reconciliation under `/api/admin/v1/games/flight/*` using existing admin security/audit middleware. The existing `apps/admin` workspace now has a Games / EXA Flight operations page.

## Account Closure

`FlightGameAccountClosureService` contributes blockers to the shared account closure safety service for unresolved entries, locked game funds, and open game risk incidents.
