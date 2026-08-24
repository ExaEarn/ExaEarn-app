# ExaEarn Deposit Processing

Deposits are credited only from blockchain/indexer evidence.

## State Machine

- `DETECTED`
- `CONFIRMING`
- `CONFIRMED`
- `CREDITED`
- `REORG_PENDING`
- `REVERSED`
- `MANUAL_REVIEW`
- `REJECTED`
- `UNSUPPORTED_ASSET_DETECTED`
- `DUST`

## Exactly-Once Identity

Deposits are unique by:

```text
network + tx_hash + event_identifier
```

For Bitcoin, the `event_identifier` should be the output index. For EVM tokens, it should be the log index. For XRP, memo/tag is stored and validated against the address assignment.

## Ledger Settlement

When confirmed, `DepositMonitoringService::creditConfirmed()` calls:

```text
SettlementService::depositCredit()
```

This posts balanced ledger entries and updates the user funding account through the canonical ledger, not through direct wallet balance mutation.

## Reorg Policy

If a stored block hash changes before credit, the deposit moves to `REORG_PENDING` and cannot be credited until operations investigate.
