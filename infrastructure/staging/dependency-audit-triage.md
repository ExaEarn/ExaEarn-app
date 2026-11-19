# Staging security readiness - 2026-11-19

## Latest validation

GitHub Actions for PR #2 commit `4f9a8984bbd6ed480ed128a5ee3ce6f0425daedd`:

- Staging run [44487641243](https://github.com/ExaEarn/ExaEarn-app/actions/runs/44487641243): Laravel safety/auth and full backend suite passed; reward-service tests passed without warning-marked tests; blockchain startup/financial-safety Jest and contract tests passed; mobile typecheck and actionlint/report-validator tests passed. Compose policy checks and both ARM64 image builds passed. The Compose job took about 5 minutes 2 seconds.
- Trivy: API image 0 HIGH/CRITICAL; blockchain image 0; staging configuration 0. Repository filesystem scan found two HIGH findings, CVE-2025-71329 and CVE-2025-71330, both on `image-size@1.2.1`. The blocking gate correctly failed.
- Developer Platform run [44487641206](https://github.com/ExaEarn/ExaEarn-app/actions/runs/44487641206): backend full suite had one flaky stale-market-data test (608 passed, 1 failed); Node audit policy failed on stale acceptance entries; production Kubernetes scan found 14 HIGH misconfigurations. Blockchain, container-image, workflow-validation, contracts/clients and CodeQL jobs passed.
- Local current lockfile audit with pinned pnpm 10.34.5: exit 1; 0 critical, 4 high, 59 moderate, 10 low. Website npm audit: exit 0, zero advisories. API-gateway and reward-service Composer audits: exit 0.
- Local API-gateway complete test suite after stabilizing its market-data fixture: 608 passed, 1 skipped, 4 PHPUnit metadata deprecation warnings; the staging login-lock test passed. Reward-service CI: 9 passed without warnings. Local Node audit-policy fixture tests passed.

The CI run above used the prior audit-policy file. This work removes all obsolete acceptances for advisories no longer returned by the current audit. The actual policy remains blocking on the current unaccepted TON/Zod advisory; no vulnerability is hidden or waived by this cleanup.

## Current pnpm HIGH findings and exposure

| Package and advisory | Path and trigger | Remediation/status |
| --- | --- | --- |
| `serialize-javascript@6.0.2`, [GHSA-5c6j-r48x-rmvq](https://github.com/advisories/GHSA-5c6j-r48x-rmvq) | `backend/services/blockchain-service > hardhat > mocha > serialize-javascript`; Hardhat test/build tooling, not the service runtime image. The advisory requires attacker-controlled RegExp flags or Date serialization output to reach a serialized string that is later evaluated. | Fixed upstream in 7.0.3. Mocha 10 constrains its dependency to 6.x; no override was shown compatible with this Hardhat/Mocha chain, so do not force it. A time-bounded tooling acceptance remains until 2026-11-29 and is reported as an open finding. |
| `image-size@1.2.1`, [GHSA-5p2g-fcmc-qvqq](https://github.com/advisories/GHSA-5p2g-fcmc-qvqq) and [GHSA-w3rx-r6r6-pgpr](https://github.com/advisories/GHSA-w3rx-r6r6-pgpr) | `react-native > @react-native/community-cli-plugin > metro > image-size`; mobile build tooling. A crafted JXL/HEIF or ICNS buffer can hang the parser. It is not present in either staging container image. | Advisory lists 2.0.3 as fixed; a local 2.0.3 override broke Expo/Metro Android export (`getImageSize is not a function`) and was reverted. Keep the two temporary, path-specific acceptances visible until Metro publishes a compatible fix; rerun Android export before changing. |
| `zod@3.25.76`, [GHSA-mrhx-c7xc-m3w5](https://github.com/advisories/GHSA-mrhx-c7xc-m3w5) | `backend/services/blockchain-service > @ton/ton > zod`. A large invalid array reaching an unconstrained Zod array schema can exhaust memory. Normal production installs include optional TON dependencies, so the main production image remains exposed if a reachable TON parsing path receives attacker-controlled input. | Advisory patches at 4.6.6; TON requires Zod 3 and no compatible patched Zod 3 release is available. It is deliberately unaccepted. Do not force Zod 4 into TON. Keep this as a production blocker pending a compatible TON fix or reviewed replacement. |

The current pnpm audit no longer reports the previously accepted Undici, adm-zip, or deepmerge-ts advisories. Their stale acceptance entries were removed. The remaining serialize-javascript and image-size acceptances are bounded and path-specific; the audit still emits their findings and Trivy continues to block on the image-size findings.

## TON/Zod staging isolation

`@ton/ton`, `@ton/crypto`, and `web3` remain optional dependencies for normal installs and the unchanged production Dockerfile. The staging-only Dockerfile installs/deploys with `--no-optional`; staging Compose selects that Dockerfile. The staging startup test asserts TON/Web3/Zod parsing modules are not loaded, and the staging production tree check found none of those packages. Staging is testnet-only and locked against signing/financial operations. These controls contain the current finding for staging; they do not fix the production dependency or clear the repository-wide pnpm audit.

## Production Kubernetes (separate from EC2 staging)

The separate Developer Platform Trivy config gate still reports 14 HIGH misconfigurations in the unchanged production manifest `infrastructure/developer-platform/kubernetes/base.yaml`: KSV-0014 (5 root filesystems not read-only) and KSV-0118 (9 missing default container security contexts). These concern production Kubernetes workloads, not the EC2 staging Compose stack. Keep the manifest unchanged in this PR and track a separate reviewed production change: verify writable paths and image UIDs, then apply non-root, read-only root filesystem, no privilege escalation, dropped capabilities, and RuntimeDefault seccomp with only verified writable mounts. Rerun Trivy and workload tests before that production change.

## Compose and deployment decision

CI verified the default Compose model starts only private PostgreSQL and Redis; neither publishes host ports. API and blockchain are behind the `application` profile, Caddy is behind `public`, and only Caddy publishes 80/443 when all profiles are selected. No financial worker is defined. Compose checks, locked testnet policy, and ARM64 builds passed.

**Decision: NO-GO for AWS staging now.** The staging image and staging-config scans are clean, and the stack defaults to locked, testnet-only operation. However, the required blocking pnpm audit still fails (including unaccepted production TON/Zod), Trivy filesystem still has two HIGH findings, and the latest CI run fails its audit gate. Do not deploy until the repository's required blocking gates pass with compatible fixes or a formally reviewed, non-suppressive scope change. The production TON/Zod issue and the separate 14-item Kubernetes gate remain open; no production or AWS changes are included here.
