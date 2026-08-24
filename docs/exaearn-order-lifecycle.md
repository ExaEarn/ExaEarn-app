# ExaEarn Spot Order Lifecycle

Date: 2026-08-17

## Canonical States

Phase 2 uses these user-facing/compatibility order states:

- `accepted`
- `open`
- `partially_filled`
- `filled`
- `cancelled`
- `rejected`

Future internal states may add:

- `cancel_pending`
- `expired`
- `settlement_pending`
- `manual_review`

## New Order Flow

```text
NEW_ORDER
  -> validate market/rules
  -> reserve funds
  -> create order as accepted
  -> assign sequence
  -> journal ORDER_ACCEPTED
  -> run matching engine
```

If fully matched:

```text
accepted -> filled
```

If partially matched and GTC:

```text
accepted -> partially_filled
```

If no match and GTC:

```text
accepted -> open
```

If IOC/FOK/market remainder is cancelled:

```text
accepted -> cancelled
```

If post-only crosses:

```text
accepted -> rejected
```

## Cancel Flow

```text
CANCEL_ORDER
  -> verify owner
  -> verify open/partially_filled/accepted state
  -> assign sequence
  -> release remaining reservation
  -> set cancelled
  -> journal ORDER_CANCELLED
  -> snapshot book
```

Duplicate cancellation of an already-cancelled order returns the current cancelled order.

## Fill Flow

For each execution:

```text
maker resting order
taker incoming order
  -> calculate execution quantity
  -> execution price = maker price
  -> update filled/remaining quantities
  -> create Trade
  -> journal TRADE_EXECUTED
  -> create settlement outbox row
  -> SettlementService::spotTrade(...)
```

## Self-Trade Prevention

Default mode:

```text
CANCEL_NEWEST
```

If an incoming order would match a resting order from the same user, the incoming order is cancelled and no trade is created.

## Time In Force

`GTC`: remainder rests.

`IOC`: immediate matched quantity executes, remainder cancels.

`FOK`: if full quantity cannot execute immediately, nothing executes and order cancels.

## Post-Only

Post-only orders must not take liquidity.

If the order crosses the current book, it is rejected and its reservation is released.

