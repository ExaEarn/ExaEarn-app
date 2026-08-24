# ExaEarn Payment Provider Architecture

Providers implement `PaymentProviderInterface`.

Capabilities:

- bank directory
- account verification
- virtual accounts
- deposits
- withdrawals
- webhooks
- provider balances
- transfer fees

Current adapters:

- `SandboxPaymentProvider` for local/testing only.
- Flutterwave, Nomba and Paystack are registered in configuration as provider states, but real adapters remain gated behind credential/configuration readiness.

Provider health is persisted in `payment_provider_health`. Unconfigured production providers are reported as `UNCONFIGURED`, not treated as live.
