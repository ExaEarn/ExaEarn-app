# PR #6 Node advisory remediation and residual risk

Review date: 2026-11-22. This is a finding record, not a security exception or scanner suppression.

## Hardhat development-tool findings

The lockfile resolved Hardhat 2.28.6 to `adm-zip@0.4.16`, and Hardhat Verify 2.1.3 plus Hardhat itself to `undici@5.29.0`. Undici 5 also brought `@fastify/busboy@2.1.1`.

- Four HIGH adm-zip advisories (GHSA-7q85-xj36-vmfc, GHSA-rcw4-f5rp-g42v, GHSA-j5f4-cc29-5x44, GHSA-8238-w5pm-2374) are fixed upstream in adm-zip 0.6.1. The scoped override keeps the Hardhat 2/plugin versions unchanged and limits the replacement to Hardhat's dependency.
- GHSA-2m5v-pch6-74hx and GHSA-qrxm-fqhp-jwvm are fixed for the Undici 6 line in 6.29.1. Both Hardhat and Hardhat Verify receive that Node 18.17+ line; this repository's CI runs Node 22.
- GHSA-x8mw-p69m-v3mx is removed from these dependency paths because Undici 6.29.1 no longer depends on the vulnerable Busboy 2 package. The application services do not take this override.
- Hardhat and Hardhat Verify are devDependencies. The blockchain production image is created with pnpm's production-only deploy, so these packages are not shipped in that image. CI compiles contracts, runs contract tests and smoke-tests the Verify CLI; it does not send deployment or verification requests to a live chain.
- adm-zip 0.6.1 has a separately reported MODERATE duplicate-entry canonicalization advisory (GHSA-mv5w-h6hm-qcp7) with no published fixed version. It remains visible and is not accepted or suppressed. Hardhat uses this package as build tooling; the compatibility and trust boundary must still be reviewed if it processes untrusted archives.

## Zod status: TON edge moved; production Web3 path still blocks release

The latest maintained upstream `@ton/ton@16.3.0` still declares `zod ^3.21.4`; there is no upstream Zod 3 security release. This PR adds a package-scoped `@ton/ton>zod: 4.6.6` override, leaving Web3 and all other Zod consumers on their declared versions. This is outside TON's declared semver range, so it is an application-level compatibility choice, not an upstream-supported TON release.

Compatibility is exercised against the actual TON SDK using the patched runtime: balance RPC object parsing (including unions and arrays), valid transaction-array parsing, malformed object and transaction rejection through TON's formatted error path, malformed-address rejection before RPC, and an isolated withdrawal-construction test with no network write. The service's Axios adapter also retains its 256 KiB response ceiling and corresponding regression test. These checks cover the SDK surface used by this service; they do not certify every public TON SDK method.

The current Node audit no longer reports the `@ton/ton > zod` edge. It now reports the same HIGH advisory through the production EVM dependency path: `backend__services__blockchain-service > web3 > web3-eth-abi > abitype > zod@3.25.76`. The current `web3-validator@2.0.6` source requires Zod 3 and imports `zod/lib/types`, which is not a supported Zod 4 entry point. Therefore this PR does not apply a Zod 4 override to Web3 or globally.

Web3's JSON-schema validator builds Zod arrays from schema inputs and parses values before returning validation errors. The vulnerability concerns resource exhaustion when large arrays contain many invalid elements; a schema's `maxItems` alone is not a reliable mitigation because Zod 3 still parses the elements. Exploitability depends on whether an externally controlled request reaches those validator calls with large malformed arrays. The affected code is in the production blockchain dependency closure, so the finding remains a release blocker.

**Residual risk / release status:** the TON edge is resolved through a scoped override with focused compatibility tests, but the production Node audit remains red for the Web3 path. Keep GHSA-mrhx-c7xc-m3w5 blocking. Resolve it through a maintained Web3/Web3 Validator release that supports patched Zod, or a reviewed replacement/migration with EVM regression coverage. Do not remove EVM functionality, force Zod 4 into Web3, or weaken the audit policy.

The separate Moderate `adm-zip@0.6.1` advisory GHSA-mv5w-h6hm-qcp7 remains visible. The upstream fix PR #588 is still open and no patched release is published. Hardhat is development-only and excluded from the production blockchain image; its compiler-download path should continue to use the pinned toolchain and authenticated HTTPS source. No scanner exception or suppression was added.

## References

- [Zod advisory GHSA-mrhx-c7xc-m3w5](https://github.com/advisories/GHSA-mrhx-c7xc-m3w5)
- [adm-zip advisories and 0.6.1 release](https://github.com/cthackers/adm-zip/releases)
- [Undici 6.29.1 security fix](https://github.com/nodejs/undici/releases)
- [TON 15.4.0 manifest](https://github.com/ton-org/ton/blob/v15.4.0/package.json)
