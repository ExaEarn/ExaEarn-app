# Staging security readiness - 2026-11-27

## Latest validation

PR #10 merged to main at `dfe204eb307dfcc32c5c456291ad42a6ea2c910f`.

- Pull-request runs [45947519409](https://github.com/ExaEarn/ExaEarn-app/actions/runs/45947519409), [45947519450](https://github.com/ExaEarn/ExaEarn-app/actions/runs/45947519450), and [45947519425](https://github.com/ExaEarn/ExaEarn-app/actions/runs/45947519425) passed. Post-merge Developer Platform and mobile runs [45949103529](https://github.com/ExaEarn/ExaEarn-app/actions/runs/45949103529) and [45949103503](https://github.com/ExaEarn/ExaEarn-app/actions/runs/45949103503) also passed.
- `pnpm audit` exited 0; the Node audit policy reported 0/0 High and `security/node-audit-acceptances.json` has no active acceptances. Gitleaks scanned 206 commits with no findings. Composer, Python, container, Compose, backend, blockchain, and client checks passed.
- Current Dependabot alerts: 22 open (0 critical, 0 high, 17 moderate, 5 low).
- All five Vercel production deployments completed successfully and all five official domains returned HTTP 200 after deployment. The separate EAS iOS and Android preview jobs errored; Expo run logs were not accessible, and no retry was started.
- Read-only AWS inventory found no deployed EC2, RDS, or ElastiCache application services, no AWS Backup plans or vaults, and no active application CloudFormation stacks. The single backup bucket is versioned, AES256-encrypted, and blocks public access, but has no lifecycle configuration. `api.exaearn.com` remains unresolved.
- No AWS resources, DNS, production configuration, or migrations were changed. Passing CI is not deployment approval; AWS staging remains NO-GO until the origin, services, backup/restore controls, monitoring, and budget are verified and approved.

## Historical pnpm HIGH findings (snapshot 2026-11-19; resolved by 2026-11-27)

The table records findings from the earlier audit snapshot. They are not current open High findings: the merged lockfile and dependency patches passed the fresh audits, and Dependabot now reports no critical or high alerts.

| Package and advisory | Path and trigger | Remediation/status |
| --- | --- | --- |
| `serialize-javascript@6.0.2`, [GHSA-5c6j-r48x-rmvq](https://github.com/advisories/GHSA-5c6j-r48x-rmvq) | `backend/services/blockchain-service > hardhat > mocha > serialize-javascript`; Hardhat test/build tooling, not the service runtime image. The advisory requires attacker-controlled RegExp flags or Date serialization output to reach a serialized string that is later evaluated. | Fixed upstream in 7.0.3. Mocha 10 constrains its dependency to 6.x; no override was shown compatible with this Hardhat/Mocha chain, so do not force it. A time-bounded tooling acceptance remains until 2026-11-29 and is reported as an open finding. |
| `image-size@1.2.1`, [GHSA-5p2g-fcmc-qvqq](https://github.com/advisories/GHSA-5p2g-fcmc-qvqq) and [GHSA-w3rx-r6r6-pgpr](https://github.com/advisories/GHSA-w3rx-r6r6-pgpr) | `react-native > @react-native/community-cli-plugin > metro > image-size`; mobile build tooling. A crafted JXL/HEIF or ICNS buffer can hang the parser. It is not present in either staging container image. | Advisory lists 2.0.3 as fixed; a local 2.0.3 override broke Expo/Metro Android export (`getImageSize is not a function`) and was reverted. Keep the two temporary, path-specific acceptances visible until Metro publishes a compatible fix; rerun Android export before changing. |
| `zod@3.25.76`, [GHSA-mrhx-c7xc-m3w5](https://github.com/advisories/GHSA-mrhx-c7xc-m3w5) | `backend/services/blockchain-service > @ton/ton > zod`. A large invalid array reaching an unconstrained Zod array schema can exhaust memory. Normal production installs include optional TON dependencies, so the main production image remains exposed if a reachable TON parsing path receives attacker-controlled input. | Advisory patches at 4.6.6; TON requires Zod 3 and no compatible patched Zod 3 release is available. It is deliberately unaccepted. Do not force Zod 4 into TON. Keep this as a production blocker pending a compatible TON fix or reviewed replacement. |

At the 2026-11-19 snapshot, the pnpm audit no longer reported the previously accepted Undici, adm-zip, or deepmerge-ts advisories. The later remediation also removed the remaining stale acceptance records and updated the affected lockfile paths.

## TON/Zod dependency status

The current lockfile uses patched `@ton/ton@15.4.0`, resolves Zod to 4.6.6, and pins Metro's `image-size` to 2.0.3. Fresh Node audit, Gitleaks, and Dependabot results show no critical or high dependency finding and no active audit acceptance. TON/Web3 remain optional dependencies for normal installs; the staging-only Dockerfile installs/deploys with `--no-optional`, and staging Compose selects that Dockerfile. Startup tests verify those parser modules are not loaded in staging. Staging remains testnet-only and locked against signing/financial operations.

## Production Kubernetes (separate from EC2 staging)

The last recorded Developer Platform Trivy config scan reported 14 HIGH misconfigurations in the unchanged production manifest `infrastructure/developer-platform/kubernetes/base.yaml`: KSV-0014 (5 root filesystems not read-only) and KSV-0118 (9 missing default container security contexts). These concern production Kubernetes workloads, not the EC2 staging Compose stack, and were not changed by #10. Keep them as an unresolved production-readiness item pending a fresh config scan and a separately reviewed production change.

## Compose and deployment decision

CI verified the default Compose model starts only private PostgreSQL and Redis; neither publishes host ports. API and blockchain are behind the `application` profile, Caddy is behind `public`, and only Caddy publishes 80/443 when all profiles are selected. No financial worker is defined. Compose checks, locked testnet policy, and ARM64 builds passed.

**Decision: NO-GO for AWS staging.** The current repository security and staging CI gates pass, but no staging application services are deployed, the API hostname is unresolved, the backup bucket has no lifecycle policy, and live restore/monitoring evidence is absent. The separate production Kubernetes findings also remain open. Obtain explicit budget and deployment approval, verify the origin and recovery controls, then rerun the production-readiness checks before deploying. No production or AWS changes are included here.
