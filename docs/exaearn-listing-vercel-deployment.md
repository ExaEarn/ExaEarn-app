# ExaEarn Listing Portal Vercel Deployment

## Project

- Vercel project name: `exaearn-listing`
- Git repository: `ExaEarn/ExaEarn-app`
- Git branch: `main`
- Root directory: `apps/listing`
- Workspace package: `@exaearn/listing`
- Framework: Vite
- Package manager: `pnpm@10.0.0`
- Install command: `cd ../.. && corepack enable && corepack prepare pnpm@10.0.0 --activate && pnpm install --frozen-lockfile`
- Build command: `cd ../.. && pnpm --filter @exaearn/listing build`
- Output directory: `dist`

## Environment

Required public frontend variables:

- `VITE_API_URL`: ExaEarn production API base URL. Current source fallback is `https://api.exaearn.com`.

No backend secrets, provider secrets, database credentials, GitHub tokens, Vercel tokens, private keys, or wallet credentials belong in this frontend project.

## Routing

The app is a Vite SPA. `apps/listing/vercel.json` rewrites all direct routes to `index.html` so refresh/navigation works for client-side routes.

## Verification

- Typecheck: `PASS`
- Local production build: `PASS`
- Production deployment: `PASS`
- Production URL: `https://exaearn-listing-seven.vercel.app`
- Project alias: `https://exaearn-listing-kendrick9470s-projects.vercel.app`
- Live verification: `PASS` for homepage, direct-route refresh, and static assets
- API connection: `FAIL` until `https://api.exaearn.com` resolves to the deployed ExaEarn backend

## Custom Domain

Preferred future custom domain: `listing.exaearn.com`.

Status: `READY_FOR_CONFIGURATION`.
