# ExaEarn Home UI Audit

## Existing Architecture

- The authenticated application is rooted in `apps/web/src/App.jsx` and uses its existing page-state router.
- The previous Home presentation lived inline in `App.jsx`; the canonical visible Home is now `features/home/UniversalHome.jsx`.
- Registration and onboarding remain in `pages/auth/Register.jsx`.
- Dashboard preferences remain in `features/dashboard/dashboardApi.js`, `DashboardCustomizer.jsx`, and the existing backend dashboard endpoints.
- No dashboard resolver, alternate Crypto dashboard, preference migration, or onboarding redesign was introduced.

## Reused Data And Routes

| Home capability | Existing source/destination |
| --- | --- |
| Portfolio | Authenticated `GET /api/portfolio` plus `portfolio:update` realtime event |
| Rewards/streak | `/api/points`, `/api/checkin`, and existing reward modal/history |
| Markets | Shared `useMarketData` subscription; no per-row sockets |
| Notifications | Existing authenticated notification APIs and tray actions |
| Earn APY | `GET /api/v1/staking/products`; shown only when backend publishes APY |
| Deposit | Existing `addFunds` page |
| Withdraw | Existing `withdraw` page |
| Send | Existing `send` page |
| Buy Crypto | Existing funding/buy/P2P entry flow |
| Spot/Futures | Existing `trade` entry page |
| ExaAI | Existing `aiAssistant` page |
| Earn | Existing `staking` page |
| ExaPay | Existing Add Funds payment experience |
| ExaCard | Existing `exacard` page |
| All Services | Existing `more` page |
| Promotion | Existing campaign/news configuration and `campaigns` page |

## Availability Findings

- Copy Trading has backend infrastructure but no complete retail web page in the current app. Home therefore exposes a truthful disabled `PRIVATE BETA` state.
- Portfolio performance is not fabricated. The 24-hour selector remains contextual until a real portfolio analytics series is published.
- Multi-Chain Vault is represented as a compact Assets link rather than a competing Home card.

## Responsive Architecture

- Mobile uses the existing fixed primary navigation and safe-area reservation.
- Core modules are deliberately bounded and compact at 375–430px widths.
- Tablet and desktop use the same modules in a centered two-column composition.
- Module boundaries make later ordering/emphasis personalization possible without introducing alternate Home pages.
