# ExaEarn Phase 14 Sandbox

## Purpose

The developer sandbox lets builders test API authentication, market-data consumption and private account reads without using real balances.

## Isolation

Sandbox faucet credits are stored in:

```text
developer_sandbox_balances
```

They are not written to:

```text
wallets
wallet_balances
accounts
ledger_entries
```

This prevents simulated funds from being confused with production customer balances.

## Faucet

Authenticated route:

```text
POST /api/developer/projects/{projectId}/sandbox/faucet
```

Rules:

- Project must have `environment=sandbox`.
- Asset must be configured in `config/developer_api.php`.
- Amount cannot exceed configured asset cap.
- Claims are rate limited to once per asset per project per hour.

## Sandbox API Keys

Sandbox keys use:

```text
exa_test_
```

Production keys use:

```text
exa_live_
```

Sandbox private balance reads return sandbox balances. Production private balance reads return real account balances.

## Production Boundary

Sandbox mode is not a paper trading exchange yet. Phase 14 foundation provides isolated sandbox balances and private API authentication. Full sandbox OMS execution can be layered onto this environment in a later controlled phase without changing key/account contracts.
