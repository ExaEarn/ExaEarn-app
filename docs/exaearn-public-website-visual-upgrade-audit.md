# ExaEarn Public Website Visual Upgrade Audit

## Scope

Audited the existing `apps/website` Vite/React public website before making the final visual-system polish pass.

## Current Architecture

- App: `apps/website`
- Entry: `apps/website/src/main.tsx`
- Styling: `apps/website/src/styles/index.css`
- Logo asset: `apps/website/src/assets/exaearn-logo.png`
- Public market source: `GET /api/v1/market/tickers`
- App handoff: `VITE_WEB_APP_URL`, falling back to `/app` in production and `http://127.0.0.1:5173` in development.
- Developer portal handoff: `VITE_DEVELOPER_PORTAL_URL`, falling back to `/developers`.
- Listing portal handoff: `VITE_LISTING_PORTAL_URL`, falling back to `/listing`.

## Reused Patterns

- The hero product preview reuses the shape of the actual ExaEarn app dashboard: UID header, portfolio panel, Deposit/Withdraw/Convert/Transfer actions, market rows, and a small ExaAI panel.
- Market tables and ticker content continue to use the existing market-data API instead of fabricated price rows.
- Product status badges use the existing public-site status model: `LIVE`, `BETA`, `IN DEVELOPMENT`, and `COMING SOON`.
- Footer links remain route-based and do not invent unverified social destinations.

## Product Availability Map

LIVE:

- Markets
- Spot
- Futures
- Convert
- P2P
- Wallet
- Developers

BETA:

- Earn
- Staking
- ExaAI
- ExaPay
- Fiat
- ExaCard
- Copy Trading
- Institutional
- ExaSkills
- NFT Marketplace
- Agriculture
- Crowdfunding
- Gift Cards

IN DEVELOPMENT:

- Mobile app
- ExaToken
- Proof of Reserves

## Findings

- The previous homepage architecture was already consolidated into a tighter exchange-first structure, but the hero still needed a stronger first-viewport trading/product signal.
- The public homepage needed clearer separation between the high-energy brand hero and calmer financial sections below.
- The market strip needed a graceful hierarchy for live data, cached stale data, loading, and unavailable states.
- Footer social links should only expose verified destinations. Telegram is the only verified social URL currently present in the audited website code.
- No backend contract changes were required.

