# ExaEarn staging security readiness - 2026-11-18

## Decision

**Ready for code review; not ready for AWS deployment.** The local Laravel and Node safety suites pass, startup rejects missing or unsafe staging configuration, staging services default to locked testnet endpoints, and Compose does not publish database/cache ports or start a worker. The pnpm audit still has 19 high findings (0 critical), and Docker/Trivy are unavailable in the current local environment. The required CI has been prepared but cannot run until this branch is pushed or opened as a pull request with approval.

## Safety posture

- Staging requires `EXAEARN_STAGING_SAFETY_LOCK=locked`, financial services and workers set to `false`, and production custody disabled.
- Laravel middleware rejects staging POST operations with `STAGING_FINANCIAL_SAFETY_LOCK`; startup checks require testnet endpoints, chain IDs, and empty signing material.
- Node validates its configuration before importing service dependencies. Financial transaction execution is denied, and the actual Node startup path skips every background service when the staging worker flag is false.
- Staging explicitly uses Base Sepolia (84532), Ethereum Sepolia (11155111), BSC testnet (97), Polygon Amoy (80002), XRPL testnet HTTP/WebSocket, Solana Devnet, and TRON Shasta. These values were checked against the [Base](https://docs.base.org/base-chain/api-reference/ethereum-json-rpc-api/eth_chainId), [Ethereum](https://ethereum.org/developers/docs/apis/json-rpc/), [BNB Chain](https://docs.bnbchain.org/bnb-smart-chain/developers/wallet-configuration/), [Polygon](https://support.polygon.technology/support/solutions/articles/82000907114-how-to-add-the-polygon-amoy-testnet-to-your-wallet), [Solana](https://solana.com/docs/references/clusters), and [TRON](https://developers.tron.network/re/docs/networks) documentation. Bitcoin is set to `testnet` with no RPC override; TON has no RPC override. Missing or unexpected values fail startup.
- Laravel's existing production RPC defaults remain intact; staging supplies and validates its own testnet settings. The Laravel image context excludes local `.env` files. The staging Compose file has no worker service. Without profiles, Compose includes only Postgres and Redis; application services require the `application` profile and Caddy's public listener requires the `public` profile. Only Caddy publishes 80/443.
- Compose defaults do not create working secret values: `APP_KEY`, database passwords, service secrets, and ACME email remain empty placeholders and must be provided through the approved secret-management process before deployment.

## Local validation

- Full Laravel backend suite: **608 passed, 1 skipped, 4,899 assertions**. The skip is the existing WebP upload test because GD WebP support is absent; PHPUnit also emitted existing doc-comment metadata deprecation warnings. The staging feature/unit tests plus `AuthFlowTest` separately passed **16 tests, 51 assertions**. Runs used a disposable `APP_KEY`; the local ignored `.env` was not modified.
- Blockchain Hardhat compile with `--force`: **36 Solidity files compiled**.
- Blockchain ESLint: **passed**.
- Blockchain Jest: **15 tests passed across 3 suites**, including actual route denial, actual worker-start suppression, unsafe RPC rejection, and unsafe entrypoint startup.
- Blockchain contract tests: **5 passed**.
- Composer audit: **exit 0**, no advisories and no abandoned packages.
- pnpm 10.34.5 audit: **exit 1**, 0 critical, 19 high, 70 moderate, 13 low across 1,601 dependencies. Full per-advisory classification is in `dependency-audit-triage.md`.
- The first combined Laravel run without an `APP_KEY` failed the five AuthFlow tests with `MissingAppKeyException`; rerunning with an ephemeral key passed all 16 selected tests. No application key was written to tracked files.
- Local YAML parsing and structural Compose policy assertions passed. Docker and Trivy are unavailable locally, so Docker Compose rendering, image builds, and Trivy scans remain unrun.

## Prepared CI

`.github/workflows/aws-staging-security.yml` adds non-deploying jobs for actionlint validation, Laravel feature/unit/full tests and missing-lock startup rejection, Node compile/lint/Jest/contract tests, Composer and pnpm audits, Compose default/profile exposure policy, Docker image builds, and Trivy image/filesystem/config scans. Audit and scan failures are not suppressed; reports are retained as CI artifacts. The workflow runs on pushes to `chore/aws-staging-prep`, pull requests to `main`, and manual dispatch.

The dependency audit and container scan jobs will currently fail on real findings by design. Do not deploy unless required jobs pass after remediations and successful follow-up scanning.

## Blockers before AWS deployment

1. Resolve the 19 pnpm high findings; the prepared workflow has no risk-acceptance bypass. Zod 3 in the production TON dependency is the runtime-package blocker; its affected route reachability is not established, so do not classify it as harmless.
2. Run the prepared workflow from GitHub and pass Docker Compose policy validation plus Trivy scans of both exact candidate images, the filesystem, and staging configuration. No local Docker/Trivy evidence exists.
3. Provide production-grade staging-only values for `APP_KEY`, database credentials, Node service secret, and ACME contact through approved secret management. The example intentionally contains blanks.
4. Review CI output and confirm image and filesystem findings before authorizing a deployment plan. No AWS resources, DNS, production settings, branch merge, or Expo PR were changed.

## Approval

This preparation does not authorize AWS provisioning or deployment. A separate deployment approval is required after all security blockers are closed; this work has not deployed anything.
