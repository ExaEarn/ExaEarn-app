# ExaEarn Developer Portal Vercel Deployment

## Project

- Vercel project name: `exaearn-developers`
- Git repository: `ExaEarn/ExaEarn-app`
- Git branch: `main`
- Root directory: `apps/developers`
- Workspace package: `@exaearn/developers`
- Framework: Vite
- Package manager: `pnpm@10.0.0`
- Install command: `cd ../.. && corepack enable && corepack prepare pnpm@10.0.0 --activate && pnpm install --frozen-lockfile`
- Build command: `cd ../.. && pnpm --filter @exaearn/developers build`
- Output directory: `dist`

## Environment

Required public frontend variables:

- `VITE_API_URL`: ExaEarn production API base URL. Current source fallback is `https://api.exaearn.com`.

No backend secrets, API-key secrets, webhook secrets, GitHub tokens, Vercel tokens, private keys, database credentials, or wallet credentials belong in this frontend project.

## Routing

The app is a Vite SPA. `apps/developers/vercel.json` rewrites all direct routes to `index.html` so refresh/navigation works for client-side routes.

## Verification

- Typecheck: `PASS`
- Local production build: `PASS`
- Production deployment: pending Vercel deployment execution
- Live verification: pending deployed URL

## Custom Domain

Preferred future custom domain: `developers.exaearn.com`.

Status: `READY_FOR_CONFIGURATION` unless the domain is attached in Vercel and DNS is verified.
