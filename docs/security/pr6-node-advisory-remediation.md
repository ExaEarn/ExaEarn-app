# PR #6 Node advisory remediation and residual risk

Review date: 2026-11-22. This is a finding record, not a security exception or scanner suppression.

## Hardhat development-tool findings

The lockfile resolved Hardhat 2.28.6 to `adm-zip@0.4.16`, and Hardhat Verify 2.1.3 plus Hardhat itself to `undici@5.29.0`. Undici 5 also brought `@fastify/busboy@2.1.1`.

- Four HIGH adm-zip advisories (GHSA-7q85-xj36-vmfc, GHSA-rcw4-f5rp-g42v, GHSA-j5f4-cc29-5x44, GHSA-8238-w5pm-2374) are fixed upstream in adm-zip 0.6.1. The scoped override keeps the Hardhat 2/plugin versions unchanged and limits the replacement to Hardhat's dependency.
- GHSA-2m5v-pch6-74hx and GHSA-qrxm-fqhp-jwvm are fixed for the Undici 6 line in 6.29.1. Both Hardhat and Hardhat Verify receive that Node 18.17+ line; this repository's CI runs Node 22.
- GHSA-x8mw-p69m-v3mx is removed from these dependency paths because Undici 6.29.1 no longer depends on the vulnerable Busboy 2 package. The application services do not take this override.
- Hardhat and Hardhat Verify are devDependencies. The blockchain production image is created with pnpm's production-only deploy, so these packages are not shipped in that image. CI compiles contracts, runs contract tests and smoke-tests the Verify CLI; it does not send deployment or verification requests to a live chain.
- adm-zip 0.6.1 has a separately reported MODERATE duplicate-entry canonicalization advisory (GHSA-mv5w-h6hm-qcp7) with no published fixed version as of this review. It remains visible and is not accepted or suppressed. Hardhat uses this package as build tooling. Treat archives as trusted inputs; do not use this toolchain to extract user-supplied archives until upstream provides a fix or a reviewed mitigation is validated.

## Zod runtime paths: TON is scoped; unused Web3 path removed

The maintained upstream `@ton/ton` manifest still declares Zod 3 (`^3.21.4`). This PR keeps the existing package-scoped `@ton/ton>zod: 4.6.6` override. It is outside TON's declared semver range, so this is an application compatibility choice, not an upstream-supported TON release.

Compatibility tests exercise the TON SDK surface used by this service with the overridden runtime: balance RPC object parsing (including unions and arrays), valid transaction-array parsing, malformed object and transaction rejection through TON's error path, malformed-address rejection before RPC, and isolated withdrawal construction with no network write. The Axios adapter retains its 256 KiB response ceiling and regression test. These checks cover this service's use, not every public TON SDK method.

The separate production Web3 path was removed rather than forcing Zod 4 into Web3. Repository audit found one Web3 import and one internal `getWeb3()` helper with no callers. The blockchain service's EVM operations use Ethers: provider/signer access, wallet derivation through the existing HD wallet service, contract reads and writes, ABI encoding/decoding, deposit monitoring, event listeners and withdrawal broadcasting. The removed helper was not exposed as an HTTP API, and repository-wide call-site searches found no internal consumers.

Web3 4's validator dependency still relies on Zod 3-specific APIs, including `zod/lib/types`; no compatible maintained Web3 release supporting patched Zod was identified. Applying a Zod 4 override there would therefore be incompatible. Removing the unused Web3 importer/dependency eliminates the vulnerable path from this application dependency graph while leaving Ethers-based EVM functionality in place.

The PR adds regression coverage for Ethers provider and signer caching, unsupported networks, missing signing keys, ERC-20 contract setup and Transfer event interface, transfer ABI encode/decode, malformed RPC return data, invalid destination/amount rejection before broadcast, unknown token rejection, and continued delegation of wallet derivation. Tests exercise rejection paths without network writes or real signing secrets.

**Current CI result:** On PR head `c5e41eb3e3cb510aa84273b6b150fcc8dd2be380`, GitHub Actions run [44991728948](https://github.com/ExaEarn/ExaEarn-app/actions/runs/44991728948) completed successfully. Its security job passed frozen pnpm installation, Gitleaks history scanning, the Node audit policy, both Composer audits and Python SDK audit. The Node policy reports five of five pre-existing HIGH findings accepted under the repository's existing time-bounded registry; this change added no acceptance. The Zod HIGH path is no longer present in the reported dependency graph. Container scans, backend, blockchain, contract/client and workflow validation jobs also passed.

**Residual risk / release status:** The GHSA-mrhx-c7xc-m3w5 Web3 dependency path is removed from the application graph, not suppressed. TON still uses an out-of-range scoped Zod 4 override whose compatibility is tested only for service-used APIs; keep monitoring upstream TON support and run the TON regression suite on SDK upgrades. The Moderate adm-zip advisory remains visible in development tooling and has no confirmed fixed release. It is not a HIGH/CRITICAL production-image finding, but trusted-archive handling remains a build-tool risk.

## References

- [Zod advisory GHSA-mrhx-c7xc-m3w5](https://github.com/advisories/GHSA-mrhx-c7xc-m3w5)
- [adm-zip advisory GHSA-mv5w-h6hm-qcp7](https://github.com/advisories/GHSA-mv5w-h6hm-qcp7)
- [adm-zip releases](https://github.com/cthackers/adm-zip/releases)
- [Undici 6.29.1 release](https://github.com/nodejs/undici/releases)
- [TON package manifest](https://github.com/ton-org/ton/blob/main/package.json)
- [Web3 validator manifest](https://github.com/web3/web3.js/blob/4.x/packages/web3-validator/package.json)
- [Web3 validator implementation](https://github.com/web3/web3.js/blob/4.x/packages/web3-validator/src/validation/validator.ts)
