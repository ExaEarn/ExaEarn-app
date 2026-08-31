# ExaEarn Developer Platform Dependency Risk Decisions

Date: 2026-08-31  
Status: SECURITY OWNER DECISION REQUIRED  
Scope: nine unaccepted High Node advisory paths remaining after compatible remediation

This document is a decision packet, not a risk acceptance. Production Access, Production webhooks, organization Production Access, and Developer wallet withdrawal remain disabled or blocked.

## Reachability summary

| Root cause | High paths | Production runtime | Build/CI | Developer workstation | Shipped vulnerable code |
|---|---:|---|---|---|---|
| Hardhat 2 toolchain | 6 | No | Yes | Yes | No: the production image copies a service-only `pnpm deploy --prod` closure and Hardhat is a devDependency |
| Prisma 7 CLI/configuration | 1 | No | Yes | Yes | No: Prisma is a root workspace dependency and is excluded from the service-only production closure |
| Metro `image-size` parser | 2 | No | Yes | Yes | No: React Native and Metro are root/mobile dependencies excluded from the service-only production closure |

All nine paths are build/development reachable and therefore remain security relevant. Candidate `5e26e6c96413409cff3ded364af26a9db4fdd880` incorrectly copied the workspace root dependency closure into the realtime image, making the Prisma and Metro findings image-resident. The corrected Dockerfile uses pnpm's portable service-only production deployment closure; a mandatory Trivy rerun must confirm the three findings are absent before runtime reachability returns to zero.

## Decision packet A: Hardhat 2 toolchain

**Advisories:** GHSA-xcpc-8h2w-3j85 (`adm-zip`), GHSA-5c6j-r48x-rmvq (`serialize-javascript`), GHSA-ph9p-34f9-6g65 (`tmp`), GHSA-vrm6-8vpv-qv8q/GHSA-v9p9-hfj2-hcw8/GHSA-vxpw-j846-p89q (`undici`)
**Installed roots:** Hardhat 2.28.6 and its Mocha/Solc/tooling graph  
**Required migration:** Hardhat 3 plus compatible ESM plugin/configuration migration  
**Exposure:** contract compilation, contract tests, deployment/verification scripts, CI, and developer machines. Hardhat is absent from the production blockchain image because the package declares it as a devDependency and the image copies a `pnpm deploy --prod` service closure.

**Exploit prerequisites:** untrusted contract projects or archives, attacker-controlled serialized/report content, unsafe temporary-file inputs, or attacker-controlled HTTP/WebSocket endpoints processed during contract tooling. The production exchange request path does not invoke Hardhat.

**Current controls:** trusted repository inputs only; explicit minimal Hardhat plugins; frozen lockfile; contract compilation validation; no production secrets in untrusted builds; non-root production image; devDependencies excluded from the runtime image.

**Why not migrated here:** Hardhat 3 changes the module/plugin contract and requires an intentional ESM migration. The blockchain service itself is CommonJS. A forced transitive override or package-wide `type: module` change would be unsafe in this narrow phase.

**Owner decision:** Blockchain Engineering and Application Security must either approve a separately tested Hardhat 3 workspace migration or formally accept the constrained build-time exposure.  
**Recommended remediation deadline:** 2026-10-31  
**Decision review/expiry:** no later than 2026-09-30; any acceptance must expire no later than 2026-11-30.

## Decision packet B: Prisma 7 tooling

**Advisory:** GHSA-ggr8-5vv4-36mx (`deepmerge-ts` 7.1.5; patched in 8.x)
**Parent:** Prisma/@prisma/client 7.8.0  
**Repository use:** `backend/database/prisma/schema.prisma`, `backend/services/blockchain-service/prisma/schema.prisma`, and their Prisma configuration files. No application import or construction of `PrismaClient` was found.

**Exploit prerequisites:** attacker influence over Prisma configuration merge inputs during schema/client tooling. No production HTTP or queue path invokes the affected configuration code.

**Current controls:** schemas and configuration are repository-controlled; frozen lockfile; Laravel/PostgreSQL remains the canonical financial runtime; production API and blockchain images do not execute Prisma CLI workflows.

**Why not migrated here:** the available Prisma 8 line is release-candidate/major migration territory. Removing Prisma would discard two active schemas; forcing `deepmerge-ts` 8 would violate the parent's declared compatibility.

**Owner decision:** Data Platform and Application Security must decide whether the schemas remain required, then either run a Prisma 8 migration after stable release or accept the constrained tooling exposure.  
**Recommended remediation deadline:** 2026-11-30  
**Decision review/expiry:** no later than 2026-09-30; any acceptance must expire no later than 2026-12-31.

## Decision packet C: Metro media parser

**Advisories:** GHSA-w3rx-r6r6-pgpr and GHSA-5p2g-fcmc-qvqq (`image-size` 1.2.1)
**Parent:** Expo 53 / React Native 0.79.6 / Metro 0.82.5  
**Patched release:** none published for the affected package according to the captured registry audit.

**Exploit prerequisites:** processing a malicious ICNS/JXL/HEIF asset during mobile bundling or developer tooling. The parser is not shipped as an exchange server runtime dependency.

**Current controls:** mobile assets are repository-reviewed; CI must not fetch or process arbitrary user media as source assets; build jobs run without production credentials; generated bundles are immutable release inputs.

**Why not migrated here:** moving to React Native/Metro 0.87 is a substantial Expo/mobile product migration and does not establish that the no-patch child advisory is eliminated. A direct override is not defensible without parent compatibility and parser tests.

**Owner decision:** Mobile Engineering and Application Security must track the upstream patch, prohibit untrusted build assets, and approve either a tested Expo/RN migration or a time-limited acceptance.  
**Recommended remediation deadline:** 2026-10-31 or within 14 days of an upstream patched compatible release, whichever occurs first.  
**Decision review/expiry:** no later than 2026-09-30; any acceptance must expire no later than 2026-11-30.

## Override review

The root override set remains at 19. Every override is constrained to a reviewed patched release; range-qualified entries preserve the parent major where multiple incompatible majors coexist. The frozen install, focused tests, production builds, Hardhat compilation, and full backend suite pass with this set.

```text
OVERRIDES BEFORE: 19
OVERRIDES AFTER:  19
UNSAFE OVERRIDES: 0
OBSOLETE OVERRIDES REMOVED: 0
```

No remaining High advisory can be safely fixed by another transitive override: each requires a parent migration or has no patched child release.

## Required decision

RC2 remains prohibited until all nine High paths are either eliminated and retested or explicitly accepted by authorized security owners with compensating controls and expiry. This report grants no acceptance.

## Independent re-review: 2026-08-31

The three packets were independently rechecked against package manifests, imports, active Prisma schemas, Metro configuration, and the blockchain production Dockerfile. Their dispositions remain unchanged:

| Root cause | Disposition | Reason |
|---|---|---|
| Hardhat | SECURITY OWNER APPROVAL REQUIRED | Six High build-tool advisories; no production runtime reachability; safe closure requires the separately tested Hardhat 3 ESM/plugin migration or authorized time-bounded acceptance |
| Prisma | SECURITY OWNER APPROVAL REQUIRED | One High CLI/config advisory; two schemas are active; Prisma 8 is a major/pre-release migration and official schema validation is network blocked |
| Metro | SECURITY OWNER APPROVAL REQUIRED | Two High build-time parser advisories with no patched child release; independent Metro forcing is unsupported by the Expo/RN compatibility line |

No authorized owner identity or approval was supplied. Consequently none of the packets is formally accepted, all nine raw High paths remain unaccepted, and software P1 remains open.

Early re-review triggers remain: a compatible patched parent release, a patched `image-size` release, changed production artifact composition, introduction of untrusted build inputs, CI privilege expansion, or any evidence of exploitability in ExaEarn's build environment.
