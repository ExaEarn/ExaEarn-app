# Staging security triage and deployment scope - 2026-11-18

## Current validation results

- pnpm audit --json with the repository-pinned pnpm 10.34.5: exit 1; 0 critical, 7 high, 59 moderate, 10 low. The report is non-empty and is not a passing audit.
- Website npm audit --json, run from an isolated copy of apps/website/package.json and its existing lockfile to avoid npm mistaking the enclosing pnpm monorepo for an npm workspace: exit 1; 4 high, 0 critical.
- Composer audits for backend/api-gateway and backend/services/reward-service: both exit 0.
- API gateway Laravel suite with temporary process-only test settings: 608 passed, 1 skipped; PHPUnit emitted four existing metadata deprecation warnings.
- Reward-service suite with a temporary dummy-key .env created only for the run and removed immediately afterward: 9 passed, no warnings.
- Blockchain Jest: 3 suites / 18 tests passed; lint passed; Hardhat compile passed; Hardhat contract tests: 5 passed.
- Website typecheck and production build passed. Mobile typecheck and Android Expo export passed.
- Trivy report validator: 9 tests passed.
- Docker and Trivy executables are unavailable locally. ARM64 image builds, Compose rendering, and Trivy results require the fresh GitHub Actions run. The baseline Trivy artifact from run 44307073969 reported 7 HIGH filesystem dependency findings and 14 HIGH Kubernetes misconfigurations; it predates this change.

## Compatible dependency remediation already validated

- @nomicfoundation/hardhat-verify 2.1.3 -> 2.1.4 and Undici 5.x -> 6.29.1 override remove the old verifier's vulnerable Undici 5 path. Blockchain Jest and Hardhat tests passed.
- adm-zip 0.4.16 -> 0.6.1 and deepmerge-ts 7.1.5 -> 8.0.1 remove the prior vulnerable instances. Prisma validate/generate and Hardhat tests passed in local validation.
- @fastify/busboy is no longer in the audited path.
- An image-size 2.0.3 override was tested and reverted: Metro/Expo failed its Android export with getImageSize is not a function. The current lock retains 1.2.1 and the Android export passes.
- No vulnerable version is ignored or risk-accepted.

## Remaining pnpm HIGH advisories

| Package / installed version / advisory | Dependency path and exposure | Patch / compatible option | Status |
| --- | --- | --- | --- |
| serialize-javascript 6.0.2, GHSA-5c6j-r48x-rmvq | backend/services/blockchain-service > hardhat > mocha > serialize-javascript; Hardhat test/build tooling only, excluded from the service production deployment. Exploitation needs attacker-controlled values to reach the serializer while the tool runs. | Patched at >=7.0.3. The current Hardhat/Mocha dependency range uses 6.x; a Mocha/Hardhat major change has not been shown compatible with this project. | High; no safe compatible upgrade verified. |
| image-size 1.2.1, GHSA-5p2g-fcmc-qvqq and GHSA-w3rx-r6r6-pgpr | react-native-worklets > react-native > @react-native/community-cli-plugin > metro > image-size; mobile build tooling only. Crafted JXL, HEIF, or ICNS data can trigger parser loops during a build. | Fixed at >=2.0.3, but the tested upgrade broke Expo's getImageSize import and was reverted. Revisit when Metro/Expo provides a compatible fix. | Two HIGH advisories; no compatible patch in the current chain. |
| zod 3.25.76, GHSA-mrhx-c7xc-m3w5 | backend/services/blockchain-service > @ton/ton > zod; ordinary production installs include optional dependencies, so the main production image can contain it. TON's RPC parsing path is lazy and not mounted by the current Express entrypoint, but future/internal calls can reach it. | The advisory fixes at >=4.6.6; TON requires Zod 3 and no compatible Zod 3 patch is available. Do not force Zod 4 into TON. | Production dependency finding remains open. |
| picomatch 2.3.2, 3.0.2, 4.0.7, GHSA-fc5q-39xc-vjpf | Admin Vite, mobile Expo CLI, and Metro/Jest tooling paths. ReDoS requires attacker-controlled glob patterns to reach the matching operation. | Advisory currently publishes no patched version. No fixed compatible release can be selected. | Three HIGH instances; audit-blocking. |

## TON / Zod staging boundary

TON and Web3 are now optionalDependencies. A normal pnpm install and the unchanged production Dockerfile install optional dependencies, preserving existing TON/Web3 support. The blockchain provider loads Web3 lazily so it does not need Web3 merely to start.

The staging-only Dockerfile.staging installs and deploys with --no-optional. A local production-deploy-tree inspection confirmed that the staging tree contains no @ton/ton, @ton/crypto, web3, or Zod package. The Node startup test also verifies the staging entrypoint does not load TON, Web3, or Zod modules. infrastructure/staging/compose.yaml now selects Dockerfile.staging; the general production Dockerfile is unchanged.

This is a staging-only containment measure. The full workspace audit still reports the production TON/Zod issue, and the main production image remains affected until TON supports a patched Zod version or TON functionality is replaced with a reviewed compatible implementation. The new ARM64 build and Trivy image scan are still pending in GitHub CI; the local package-tree check is not a substitute for those scans.

## Website npm HIGH findings

The isolated website lockfile audit reports fdir 6.5.0, picomatch 4.0.7, tinyglobby 0.2.17, and Vite 6.4.6 as four HIGH package findings through the picomatch advisory. The registry reports no patched picomatch release. npm suggests changing Vite to 6.2.7 (a downgrade from the locked Vite 6.4.6); that suggestion does not provide a verified compatible fix for the underlying unpatched picomatch advisory. No website dependency was changed.

## Kubernetes findings tracked separately from EC2 staging

The baseline Trivy filesystem report identified 14 HIGH misconfigurations in infrastructure/developer-platform/kubernetes/base.yaml, which defines production Developer Platform workloads. This staging change leaves that manifest unchanged and runs a separate, blocking Trivy config job against that path in developer-platform-gates.yml; its JSON report is uploaded even on findings. The staging filesystem scan is limited to vulnerability/secret scanning, while the Kubernetes findings remain visible to the repository's independent blocking config scan.

The 14 baseline findings are:
- KSV-0014 (5): root filesystem is not read-only on api-gateway, realtime-gateway, scheduler, queue-worker, and webhook-worker.
- KSV-0118 (9): containers in those workloads lack explicit security contexts.

Proposed follow-up, in a separate Developer Platform change: verify image users and writable paths; set runAsNonRoot, a verified UID/GID, allowPrivilegeEscalation: false, readOnlyRootFilesystem: true, capabilities.drop: [ALL], and seccompProfile: RuntimeDefault; add only confirmed writable emptyDir mounts; render manifests and rerun the pinned Trivy config scan with workload tests. Do not modify production manifests as part of this staging PR.

## Compose and deployment decision

The Compose model keeps PostgreSQL and Redis private (no host ports), starts only those two services by default, and places API/blockchain behind the application profile and Caddy behind the public profile. No financial worker or queue service is defined. With all profiles selected, only Caddy publishes ports 80/443. CI additionally asserts the blockchain service selects Dockerfile.staging.

Decision: NO-GO for AWS staging at this point. The local unit/build tests pass, but the dependency audits still return blocking HIGH findings, production TON/Zod remains unresolved, and the fresh ARM64 image/Compose/Trivy CI results are not available yet. Do not deploy until the required runtime scans pass, dependency audit blockers have a reviewed compatible disposition, and staging secrets (including APP_KEY and database password) are supplied through the approved secret manager rather than committed files. Production deployment is also blocked by the production TON/Zod finding and the separate Developer Platform Kubernetes gate.
