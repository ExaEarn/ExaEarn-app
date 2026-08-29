# EXA Flight Mobile

## Scope

The existing React Native dashboard already had a Games entry point. It now surfaces EXA Flight status without rebuilding the mobile app.

## Mobile Behavior

When the Games panel is selected, mobile shows:

- `EXA Flight Demo`
- `Free play only`
- Explicit no-withdrawable-value wording
- Current backend round number
- Round state/status
- Backend-authoritative realtime/reconnect messaging
- Fairness disclosure: seed hash plus reveal
- Server-side safety note for future real-money mode

## Data Source

The dashboard requests:

```text
GET /api/games/flight/state
```

The backend state remains authoritative. Mobile does not fabricate successful entries or financial balances.

## Real-Money

Real-money EXA Flight remains disabled. Mobile labels demo credits separately and does not present them as USD, USDT, NGN or withdrawable ExaEarn assets.
