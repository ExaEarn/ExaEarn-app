# ExaEarn Games / Web3 Gaming Audit

## Maturity

Current level: Level 2 software functional; Level 4 public operations not ready.

## What Exists

- Web game page and mobile-oriented UI components.
- Public flight state/history/fairness endpoints and authenticated play/cashout routes.
- `FlightGameService` with round creation, fairness, realtime publishing, idempotent entry, auto cashout, loss settlement, and admin summary.
- Ledger movements from funding to game locked, cashout payout, and treasury settlement.
- Tests for state, lock, idempotency, closed window rejection, cashout, auto cashout, and ledger behavior.

## Production Blockers

- The service and tables still use bet/wager/stake terminology and economics.
- Product appears gambling-like and needs jurisdiction gating, age/identity controls, responsible gaming controls, loss limits, treasury exposure controls, and legal licensing before public release.
- Treasury negative exposure risk must be operationally monitored.
- Game fairness is software-backed, but external audit/provably-fair disclosure and operational controls are still required.

## Required Next Work

1. Decide whether EXA Flight is entertainment, rewards, or regulated gambling.
2. Add jurisdiction, KYC, age, limits, self-exclusion, AML, fraud, and responsible gaming policy enforcement.
3. Add treasury exposure controls and reconciliation reports.
4. Rename product language only after legal classification is decided.

