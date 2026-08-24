# ExaEarn Phase 15C Operations Runbook

## Market Maker Underfunded

1. Check `/api/admin/v1/market-makers/profiles/{profileUuid}/capital/{symbol}`.
2. Confirm canonical subaccount ledger balances.
3. Require funding or reduce assignment commitments.
4. Do not mark listing liquidity ready until the capital check passes.

## Quote Quality Degraded

1. Run market health snapshot.
2. Review spread/depth reasons.
3. Contact market maker or set `NEW_RISK_DISABLED`.
4. Use mass cancel for unsafe Phase 8 quote records.

## Emergency Pause

1. Set profile safety mode to `PAUSED` or `EMERGENCY`.
2. Mass cancel active quote records.
3. Confirm OMS live orders are cancelled through normal trading controls where applicable.
4. Keep reduce-only/risk-reducing actions available where product rules support them.

## Rebate Settlement

1. Accrue the rebate for an immutable period.
2. Review eligible/disqualified volume.
3. Pay through ledger settlement.
4. Verify the rebate period has one settlement reference.
