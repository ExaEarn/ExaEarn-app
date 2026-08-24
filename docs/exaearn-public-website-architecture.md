# ExaEarn Public Website Architecture

## Application

The public website remains the existing `@exaearn/website` Vite/React application in `apps/website`.

## Routing

The app uses lightweight path-based rendering from `window.location.pathname`, consistent with the existing `/institutional` page approach. Added public pages:

- `/markets`
- `/markets/{symbol}`
- `/fees`
- `/status`
- `/security`
- `/legal`
- `/terms`
- `/privacy`
- `/risk`
- `/restricted-jurisdictions`
- `/about`
- `/support`
- `/developers`
- `/listing`

## Data Flow

Public market surfaces call ExaEarn backend APIs:

```text
Website
  -> /api/v1/market/tickers
  -> /api/v1/market/order-book/{symbol}
  -> /api/v1/market/trades/{symbol}
  -> /api/v1/market/health
  -> /api/v1/pricing/fees
```

No frontend-only prices, order books, trades, fees, users, volume, liquidity or uptime values are generated.

## Configuration

- `VITE_API_URL` controls the Laravel API base URL.
- `VITE_WEB_APP_URL` controls the authenticated exchange app URL.
- `VITE_DEVELOPER_PORTAL_URL` controls the developer portal URL.
- `VITE_LISTING_PORTAL_URL` controls the listing portal URL.

When portal URLs are not configured, the website provides gateway pages at `/developers` and `/listing`.
