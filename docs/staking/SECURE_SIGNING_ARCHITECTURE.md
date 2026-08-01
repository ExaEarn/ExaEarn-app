# Secure Signing Architecture

Laravel must not store plaintext private keys, seed phrases, raw signing keys, validator keys, or withdrawal credentials. Providers create unsigned payloads and send signing requests through `SecureSignerInterface`.

Mainnet activation requires HSM, MPC, KMS, or qualified custody integration plus dual administrator approval.

Implemented signer adapter:

`App\Domain\Staking\Services\HttpSecureSigner`

Required environment:

`STAKING_SECURE_SIGNER_URL`
`STAKING_SECURE_SIGNER_KEY_REFERENCE`
`STAKING_SECURE_SIGNER_SECRET`
`STAKING_SECURE_SIGNER_TIMEOUT_SECONDS`

The adapter posts unsigned payloads to `/sign` with `Idempotency-Key` and `X-Signer-Secret` headers, validates that the signer returns `signed_payload` and `signing_reference`, and fails closed when configuration or response shape is invalid. The signer secret must never be logged or stored in docs.
