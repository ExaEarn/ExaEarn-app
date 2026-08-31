# ExaEarn Developer Platform Dependency Risk Decisions

Date: 2026-08-31  
Status: APPROVED - TIME-BOUNDED ADVISORY-SPECIFIC ACCEPTANCE
Scope: nine High Node advisory paths approved individually after compatible remediation

This document records the three supplied human decisions. Each approval is limited to its listed GHSA identifiers, expires automatically on 2026-11-29, and does not authorize Production Access, Production webhooks, organization Production Access, or Developer wallet withdrawal.

## Reachability summary

| Root cause | High paths | Production runtime | Build/CI | Developer workstation | Shipped vulnerable code |
|---|---:|---|---|---|---|
| Hardhat 2 toolchain | 6 | No | Yes | Yes | No: the production image copies a service-only `pnpm deploy --prod` closure and Hardhat is a devDependency |
| Prisma 7 CLI/configuration | 1 | No | Yes | Yes | No: Prisma is a root workspace dependency and is excluded from the service-only production closure |
| Metro `image-size` parser | 2 | No | Yes | Yes | No: React Native and Metro are root/mobile dependencies excluded from the service-only production closure |

All nine paths are build/development reachable and therefore remain security relevant. Candidate `5e26e6c96413409cff3ded364af26a9db4fdd880` incorrectly copied the workspace root dependency closure into the realtime image. Candidate `10a83a149ae5d96edeb7a77cbf35778099a0bda8` corrected that composition; GitHub Actions run `33413628590` rebuilt both production images and reported zero Critical and zero High findings from Trivy.

## HARDHAT SECURITY DECISION

**Advisories:** GHSA-xcpc-8h2w-3j85 (`adm-zip`), GHSA-5c6j-r48x-rmvq (`serialize-javascript`), GHSA-ph9p-34f9-6g65 (`tmp`), GHSA-vrm6-8vpv-qv8q/GHSA-v9p9-hfj2-hcw8/GHSA-vxpw-j846-p89q (`undici`)
**Installed roots:** Hardhat 2.28.6 and its Mocha/Solc/tooling graph  
**Required migration:** Hardhat 3 plus compatible ESM plugin/configuration migration  
**Exposure:** contract compilation, contract tests, deployment/verification scripts, CI, and developer machines. Hardhat is absent from the production blockchain image because the package declares it as a devDependency and the image copies a `pnpm deploy --prod` service closure.

| Package | Installed | Fixed | Relationship | Representative path |
|---|---:|---:|---|---|
| `serialize-javascript` | 6.0.2 | >=7.0.3 | Transitive | blockchain service -> Hardhat -> Mocha |
| `undici` | 5.29.0 | >=6.27.0 | Transitive | blockchain service -> Hardhat verification tooling |
| `tmp` | 0.0.33 | >=0.2.6 | Transitive | blockchain service -> Hardhat -> Solc |
| `adm-zip` | 0.4.16 | >=0.6.0 | Transitive | blockchain service -> Hardhat |

**Reachability:** production runtime NO; build time YES; CI YES; developer machine YES; direct user input NO; untrusted repository input CONDITIONAL; network CONDITIONAL through Hardhat verification/client tooling.

**Exploit prerequisites:** untrusted contract projects or archives, attacker-controlled serialized/report content, unsafe temporary-file inputs, or attacker-controlled HTTP/WebSocket endpoints processed during contract tooling. The production exchange request path does not invoke Hardhat.

**Current controls:** trusted repository inputs only; explicit minimal Hardhat plugins; frozen lockfile; contract compilation validation; no production secrets in untrusted builds; non-root production image; devDependencies excluded from the runtime image.

**Why not migrated here:** Hardhat 3 changes the module/plugin contract and requires an intentional ESM migration. The blockchain service itself is CommonJS. A forced transitive override or package-wide `type: module` change would be unsafe in this narrow phase.

**Residual risk:** a malicious contract/configuration/archive or hostile build endpoint could reach vulnerable tooling in CI or on a developer machine. Production requests cannot invoke this toolchain.

**Recommended decision:** ELIGIBLE FOR TIME-BOUNDED SECURITY-OWNER RISK ACCEPTANCE covering only the six listed GHSAs, expiring no later than 2026-11-30. The required milestone is a separately tested Hardhat 3 ESM/plugin migration by 2026-10-31. Acceptance is invalidated immediately by untrusted fork CI with secrets, unreviewed contract/configuration inputs, a compatible patched Hardhat 2 release, production-image inclusion, or evidence of active exploitation.

**Owner decision:** Blockchain Engineering and Application Security must either approve a separately tested Hardhat 3 workspace migration or formally accept the constrained build-time exposure.  
**Recommended remediation deadline:** 2026-10-31  
**Decision review/expiry:** no later than 2026-09-30; any acceptance must expire no later than 2026-11-30.

**Recorded approval:** APPROVED - TIME-BOUNDED RISK ACCEPTANCE
**Reviewer:** Theophilus Chukwuemeka, Security Owner / Application Security Reviewer
**Reference:** EXAEARN-SEC-2026-HARDHAT-001
**Approval date:** 2026-08-31
**Expiration:** 2026-11-29
**Exact scope:** the six Hardhat GHSAs listed above; no package, advisory, environment, or future finding is implicitly accepted.

## PRISMA SECURITY DECISION

**Advisory:** GHSA-ggr8-5vv4-36mx (`deepmerge-ts` 7.1.5; patched in 8.x)
**Parent:** Prisma/@prisma/client 7.8.0  
**Repository use:** `backend/database/prisma/schema.prisma`, `backend/services/blockchain-service/prisma/schema.prisma`, and their Prisma configuration files. No application import or construction of `PrismaClient` was found.

**Dependency path:** root workspace -> Prisma 7.8.0 -> `@prisma/config` -> `deepmerge-ts` 7.1.5. The affected package is transitive and fixed in 8.0.0.

**Reachability:** production runtime NO; build time YES; CI YES; developer machine YES; direct user input NO; untrusted repository input CONDITIONAL through Prisma configuration; network NO for the vulnerable merge operation.

**Exploit prerequisites:** attacker influence over Prisma configuration merge inputs during schema/client tooling. No production HTTP or queue path invokes the affected configuration code.

**Current controls:** schemas and configuration are repository-controlled; frozen lockfile; Laravel/PostgreSQL remains the canonical financial runtime; production API and blockchain images do not execute Prisma CLI workflows.

**Why not migrated here:** the available Prisma 8 line is release-candidate/major migration territory. Removing Prisma would discard two active schemas; forcing `deepmerge-ts` 8 would violate the parent's declared compatibility.

**Residual risk:** malicious repository-controlled Prisma configuration containing recursive object graphs could exhaust the build process stack. The Laravel/PostgreSQL financial runtime does not execute Prisma configuration.

**Recommended decision:** ELIGIBLE FOR TIME-BOUNDED SECURITY-OWNER RISK ACCEPTANCE for GHSA-ggr8-5vv4-36mx only, expiring no later than 2026-12-31. The required milestone is removal of unused Prisma schemas or a tested stable Prisma 8 migration by 2026-11-30. Acceptance is invalidated by runtime Prisma adoption, untrusted schema/config input, a compatible Prisma 7 fix, production-image inclusion, or exploitation evidence.

**Owner decision:** Data Platform and Application Security must decide whether the schemas remain required, then either run a Prisma 8 migration after stable release or accept the constrained tooling exposure.  
**Recommended remediation deadline:** 2026-11-30  
**Decision review/expiry:** no later than 2026-09-30; any acceptance must expire no later than 2026-12-31.

**Recorded approval:** APPROVED - TIME-BOUNDED RISK ACCEPTANCE
**Reviewer:** Calistus Anwara, Software Supply-Chain Security Reviewer
**Reference:** EXAEARN-SEC-2026-PRISMA-001
**Approval date:** 2026-08-31
**Expiration:** 2026-11-29
**Exact scope:** GHSA-ggr8-5vv4-36mx only; no package, advisory, environment, or future finding is implicitly accepted.

## METRO SECURITY DECISION

**Advisories:** GHSA-w3rx-r6r6-pgpr and GHSA-5p2g-fcmc-qvqq (`image-size` 1.2.1)
**Parent:** Expo 53 / React Native 0.79.6 / Metro 0.82.5  
**Patched release:** none published for the affected package according to the captured registry audit.

**Dependency path:** root/mobile workspace -> React Native 0.79.6 -> community CLI plugin -> Metro 0.82.5 -> `image-size` 1.2.1. The affected package is transitive.

**Reachability:** production server runtime NO; mobile build time YES; CI YES; developer machine YES; direct application user input NO; untrusted repository asset input YES; network NO unless the build first downloads an untrusted asset.

**Exploit prerequisites:** processing a malicious ICNS/JXL/HEIF asset during mobile bundling or developer tooling. The parser is not shipped as an exchange server runtime dependency.

**Current controls:** mobile assets are repository-reviewed; CI must not fetch or process arbitrary user media as source assets; build jobs run without production credentials; generated bundles are immutable release inputs.

**Why not migrated here:** moving to React Native/Metro 0.87 is a substantial Expo/mobile product migration and does not establish that the no-patch child advisory is eliminated. A direct override is not defensible without parent compatibility and parser tests.

**Residual risk:** a malicious ICNS, JXL, or HEIF source asset could hang a mobile build or developer process. It cannot affect the deployed API or realtime container.

**Recommended decision:** ELIGIBLE FOR TIME-BOUNDED SECURITY-OWNER RISK ACCEPTANCE for GHSA-w3rx-r6r6-pgpr and GHSA-5p2g-fcmc-qvqq only, expiring no later than 2026-11-30. The required milestone is an upstream patched parser or tested compatible Expo/React Native migration by 2026-10-31. Acceptance is invalidated by arbitrary remote assets entering builds, privileged fork CI, a compatible upstream patch, server-image inclusion, or exploitation evidence.

**Owner decision:** Mobile Engineering and Application Security must track the upstream patch, prohibit untrusted build assets, and approve either a tested Expo/RN migration or a time-limited acceptance.  
**Recommended remediation deadline:** 2026-10-31 or within 14 days of an upstream patched compatible release, whichever occurs first.  
**Decision review/expiry:** no later than 2026-09-30; any acceptance must expire no later than 2026-11-30.

**Recorded approval:** APPROVED - TIME-BOUNDED RISK ACCEPTANCE
**Reviewer:** Reginald Ejike, Release Security & CI Reviewer
**Reference:** EXAEARN-SEC-2026-METRO-001
**Approval date:** 2026-08-31
**Expiration:** 2026-11-29
**Exact scope:** GHSA-w3rx-r6r6-pgpr and GHSA-5p2g-fcmc-qvqq only; no package, advisory, environment, or future finding is implicitly accepted.

## Override review

The root override set remains at 19. Every override is constrained to a reviewed patched release; range-qualified entries preserve the parent major where multiple incompatible majors coexist. The frozen install, focused tests, production builds, Hardhat compilation, and full backend suite pass with this set.

```text
OVERRIDES BEFORE: 19
OVERRIDES AFTER:  19
UNSAFE OVERRIDES: 0
OBSOLETE OVERRIDES REMOVED: 0
```

No remaining High advisory can be safely fixed by another transitive override: each requires a parent migration or has no patched child release.

## Verified compensating controls

Repository evidence verifies a committed lockfile, frozen-lockfile CI installation, minimal workflow permissions (`contents: read`, `security-events: write`), Gitleaks, CodeQL, independent Node/Composer/Python audits, four CycloneDX SBOMs, candidate-SHA binding and hash manifest, non-root production containers, and Trivy enforcement at Critical/High with no ignored unfixed findings. Run `33413628590` verifies zero Critical/High production-image findings. Branch protection, reviewer enforcement, external CI secret policy, and short-lived credential configuration are deployment/GitHub settings and are not claimed as repository-proven controls.

## Advisory-specific CI decision mechanism

`scripts/validate-node-audit.mjs` runs the canonical audit and compares every current Critical/High advisory with `security/node-audit-acceptances.json`. Critical findings are never accepted. A High finding passes only when its exact GHSA identifier has a current `approved` record containing package and root-cause identity, an attributable approver and release role, approval reference, approval timestamp, expiry, rationale, compensating controls, repository, and 40-character approval commit. Expired records, stale records not present in the audit, duplicate records, wrong repositories, missing identifiers, and every new High fail closed. The registry contains only the nine approvals recorded above.

Authorized owners must add one record per approved GHSA through normal reviewed Git history. They must not group unknown future advisories, use wildcard identifiers, or extend expiry without a fresh decision. `scripts/tests/node-audit-policy-fixtures.mjs` covers valid, missing, expired, Critical, stale, unattributed, and cross-repository cases.

## Release condition

The human decision prerequisite is satisfied for these nine exact High findings. RC2 remains prohibited until the mandatory workflow validates the registry and all other repository-controlled release gates against the resulting immutable candidate.

## Independent re-review: 2026-08-31

The three packets were independently rechecked against package manifests, imports, active Prisma schemas, Metro configuration, and the blockchain production Dockerfile. The supplied human decisions are:

| Root cause | Disposition | Reason |
|---|---|---|
| Hardhat | APPROVED THROUGH 2026-11-29 | Theophilus Chukwuemeka; EXAEARN-SEC-2026-HARDHAT-001; six exact High build-tool advisories only |
| Prisma | APPROVED THROUGH 2026-11-29 | Calistus Anwara; EXAEARN-SEC-2026-PRISMA-001; GHSA-ggr8-5vv4-36mx only |
| Metro | APPROVED THROUGH 2026-11-29 | Reginald Ejike; EXAEARN-SEC-2026-METRO-001; two exact High build-time parser advisories only |

All nine current High findings are covered by exact, attributable, time-bounded records. The automated policy still fails closed on expiry, changed advisory identity, any new High, or any Critical finding. This decision does not alter production-runtime controls or authorize deployment.

Early re-review triggers remain: a compatible patched parent release, a patched `image-size` release, changed production artifact composition, introduction of untrusted build inputs, CI privilege expansion, or any evidence of exploitability in ExaEarn's build environment.
