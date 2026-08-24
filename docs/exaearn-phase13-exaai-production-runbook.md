# ExaEarn Phase 13 ExaAI Production Runbook

## Market Data Outage

1. Operations engine detects stale ExaAI decisions.
2. State moves to `NEW_RISK_DISABLED`.
3. New risk-increasing decisions are blocked.
4. Existing user/manual risk controls remain available.
5. Resume only after feed health and reconciliation pass.

## OMS Or Risk Engine Uncertainty

Set ExaAI to `NEW_RISK_DISABLED`. Do not resubmit unknown orders blindly. Reconcile OMS state first.

## Liquidity Deterioration

Run market auto-disable. Unsafe markets move to `paused` for ExaAI new exposure. Existing positions follow product-specific risk-reduction policy.

## Reconciliation Mismatch

Critical mismatch opens a `SEV1` incident and sets `EMERGENCY`. Do not auto-repair accounting. Use canonical ledger/reconciliation workflow.

## Queue Backlog

Backlog beyond threshold moves ExaAI to `NEW_RISK_DISABLED`. Risk-reducing work may continue where product engines support it.

## Strategy Runaway Or Drawdown

Use strategy governance to move strategy version to `RESTRICTED` or `PAUSED`. Do not silently liquidate users because a strategy is paused.

## Emergency Pause

Set global state to `EMERGENCY`. Safe resume requires unresolved `SEV1`/`SEV2` incidents to be resolved and readiness to pass.
