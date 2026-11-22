# PR #6 Node advisory remediation and residual risk

Review date: 2026-11-22. This is a finding record, not a security exception or scanner suppression.

## Hardhat development-tool findings

The lockfile resolved Hardhat 2.28.6 to `adm-zip@0.4.16`, and Hardhat Verify 2.1.3 plus Hardhat itself to `undici@5.29.0`. Undici 5 also brought `@fastify/busboy@2.1.1`.

- Four HIGH adm-zip advisories (GHSA-7q85-xj36-vmfc, GHSA-rcw4-f5rp-g42v, GHSA-j5f4-cc29-5x44, GHSA-8238-w5pm-2374) are fixed upstream in adm-zip 0.6.1. The scoped override keeps the Hardhat 2/plugin versions unchanged and limits the replacement to Hardhat's dependency.
- GHSA-2m5v-pch6-74hx and GHSA-qrxm-fqhp-jwvm are fixed for the Undici 6 line in 6.29.1. Both Hardhat and Hardhat Verify receive that Node 18.17+ line; this repository's CI runs Node 22.
- GHSA-x8mw-p69m-v3mx is removed from these dependency paths because Undici 6.29.1 no longer depends on the vulnerable Busboy 2 package. The application services do not take this override.
- Hardhat and Hardhat Verify are devDependencies. The blockchain production image is created with pnpm's production-only deploy, so these packages are not shipped in that image. CI compiles contracts, runs contract tests and smoke-tests the Verify CLI; it does not send deployment or verification requests to a live chain.
- adm-zip 0.6.1 has a separately reported MODERATE duplicate-entry canonicalization advisory (GHSA-mv5w-h6hm-qcp7) with no published fixed version. It remains visible and is not accepted or suppressed. Hardhat uses this package as build tooling; the compatibility and trust boundary must still be reviewed if it processes untrusted archives.

## Remaining HIGH: Zod 3 in production dependencies

The lock resolves `@ton/ton@15.4.0 -> zod@3.25.76`. The blockchain service also resolves Zod 3 through `web3-validator@2.0.6` and Web3's EVM packages. GHSA-mrhx-c7xc-m3w5 affects versions below 4.6.6; upstream has not released a patched Zod 3, and the TON 15.4.0 and current 16.3.0 manifests both require Zod 3. A blanket Zod 4 override is therefore not applied.

The installed TON library's `HttpApi` validates remote RPC responses with Zod array schemas (including account extra-currency and transaction responses). Application paths construct `TonClient` in `NonEvmChainService.getTonClient`, use it for balance reads and withdrawal broadcasts, and derive TON addresses through `HdWalletService.deriveTonAddress`. This identifies a provider-response parsing surface, but does not prove an unauthenticated exploit. Reachability depends on which service operations are exposed, their authorization, and the configured RPC provider's behavior. The HTTP service's incoming JSON parser has a finite body limit; that does not cap RPC responses.

A 256 KiB Axios response ceiling is applied to the TON RPC adapter and covered by a unit test. It reduces the amount of untrusted RPC data reaching Zod but does not patch Zod or clear the advisory. Web3's Zod dependency remains unaffected by that TON-only limit.

**Residual risk / release status:** keep GHSA-mrhx-c7xc-m3w5 visible and blocking in the full production dependency audit. Before release, obtain an upstream-compatible Zod 3 patch, replace/migrate affected production consumers with regression coverage, or complete a separately approved, time-limited risk decision. Do not remove TON/Web3 features or weaken the audit policy as a workaround.

## References

- [Zod advisory GHSA-mrhx-c7xc-m3w5](https://github.com/advisories/GHSA-mrhx-c7xc-m3w5)
- [adm-zip advisories and 0.6.1 release](https://github.com/cthackers/adm-zip/releases)
- [Undici 6.29.1 security fix](https://github.com/nodejs/undici/releases)
- [TON 15.4.0 manifest](https://github.com/ton-org/ton/blob/v15.4.0/package.json)
