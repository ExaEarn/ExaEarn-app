# ExaEarn Staking Architecture

ExaEarn Staking replaces legacy XRP/paper staking with a shared Native Proof-of-Stake domain for SOL, ETH, ADA, BNB, AVAX, SUI, DOT, ATOM, NEAR, XTZ, and POL.

Current implementation status: the Laravel schema, API surface, provider contracts, fail-closed provider registry, ledger reservation flow, admin permission map, and legacy XRP audit command are implemented. Chain providers are registered but intentionally not marked ready until real RPC endpoints, secure signing, custody wallets, validator allowlists, and end-to-end testnet evidence are configured.

XRP, BTC, USDT, USDC, and Pi are excluded from Native PoS Staking.
