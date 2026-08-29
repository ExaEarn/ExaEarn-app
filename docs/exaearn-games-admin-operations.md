# EXA Flight Admin Operations

## Admin Surface

The existing `apps/admin` workspace includes:

```text
Games / EXA Flight
```

The page reads real backend data from:

- `GET /api/admin/v1/games/flight/summary`
- `GET /api/admin/v1/games/flight/reconciliation`
- `POST /api/admin/v1/games/flight/control`

## Visible Sections

- Overview metrics
- Product classification and mode
- Real-money disabled status
- Current round detail
- Treasury exposure by asset
- Reconciliation findings
- Responsible-gaming guardrails
- Emergency controls

## Emergency Controls

Supported controls:

- `PAUSE_NEW_ENTRIES`
- `DISABLE_REAL_MONEY`
- `RESUME_DEMO_MODE`
- `CANCEL_ROUND`

Controls route through existing admin authentication, security, audit and throttling middleware. The admin UI does not expose outcome editing, historical seed editing, or confiscation actions.

## Real-Money Activation

Direct one-step activation through the settings endpoint is blocked. Enabling real-money mode requires external legal readiness and a maker-checker workflow before public use.
