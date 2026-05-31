# ExaEarn Monorepo Architecture

ExaEarn is organized as an enterprise monorepo with clear product, platform, infrastructure, and shared-package boundaries.

## Product Apps

- `apps/website` - marketing and landing website.
- `apps/web` - main authenticated user dashboard.
- `apps/admin` - standalone admin operations console.
- `apps/mobile` - reserved for the future mobile client.

## Backend And Platform

- `backend/api-gateway` - primary Laravel API gateway and transaction platform.
- `backend/services/reward-service` - secondary Laravel service focused on rewards and cross-chain primitives.
- `backend/services/blockchain-service` - Node.js blockchain monitoring and broadcasting service.
- `backend/shared`, `backend/database`, `backend/queues`, `backend/websocket` - shared backend platform boundaries.

## Blockchain And Web3

- `blockchain` - smart contracts, deployment scripts, tests, audits, and Hardhat config.
- `web3` - frontend/provider-facing wallet adapters, contract clients, listeners, hooks, and transaction utilities.

## Shared Packages

- `packages/ui` - reusable UI primitives and design-system exports.
- `packages/types` - shared TypeScript contracts.
- `packages/config` - shared environment and application config.
- `packages/sdk` - future client SDK for ExaEarn APIs.
- `packages/tsconfig` and `packages/eslint-config` - shared developer tooling.

## Commands

Use pnpm and Turborepo for normal monorepo development:

```bash
pnpm install
pnpm build
pnpm web:dev
pnpm admin:dev
pnpm website:dev
```

Direct pnpm filter fallbacks are available for the moved Vite apps:

```bash
pnpm --filter @exaearn/web build
pnpm --filter @exaearn/admin build
pnpm --filter @exaearn/website build
```
