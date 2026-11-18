# Staging CI security scans

The workflow at `.github/workflows/aws-staging-security.yml` validates Laravel and Node behavior, audits the locked dependencies, validates default and fully profiled Docker Compose models, builds both staging images, then runs Trivy image, filesystem, and infrastructure configuration scans. All build and scan steps run independently where possible, and a final aggregate gate fails if any step fails or does not run; no scanner is configured to ignore unfixed advisories. Reports are uploaded even when a scan fails.

Docker Compose validation asserts that default Compose starts only Postgres and Redis, those data services and both app containers publish no host ports, only Caddy publishes 80/443 under the explicit `public` profile, application services require the `application` profile, no worker service exists, and lock/service/custody/RPC/network/chain-ID environment values are the staging-safe values.

The current local environment has no Docker or Trivy executable, so CI must provide the following evidence before deployment:

1. `docker compose -f infrastructure/staging/compose.yaml config --quiet` with default profiles and `docker compose -f infrastructure/staging/compose.yaml --profile application --profile public config --format json` plus the workflow policy assertions.
2. Build `exaearn-staging-api:<candidate-sha>` from `backend/api-gateway/Dockerfile` and `exaearn-staging-blockchain:<candidate-sha>` from `backend/services/blockchain-service/Dockerfile`.
3. `trivy image --severity HIGH,CRITICAL --exit-code 1` on both exact built image references.
4. `trivy fs --scanners vuln,secret,misconfig --severity HIGH,CRITICAL --exit-code 1 .` and `trivy config --severity HIGH,CRITICAL --exit-code 1 infrastructure/staging`.
5. `composer audit --locked --abandoned=fail` and `pnpm audit --json`, preserving the JSON output and actual exit code. Empty audit output must fail.
6. Laravel staging feature/unit/auth tests, full `php artisan test`, staging startup with a missing lock (must fail); Node Hardhat compile, lint, all Jest tests, and contract tests.

Do not deploy unless every required CI job passes and security owners review any report. The current pnpm audit has 19 high findings and therefore the dependency-audit job is expected to fail until remediated.
