# ExaEarn Signing Security

Phase 9 introduces `SigningProviderInterface`.

Production signing must use:

- MPC
- HSM
- Multisig
- Secure external signer

The included `DevelopmentSigningProvider` is only allowed in `local` and `testing`. If `CUSTODY_PRODUCTION_ENABLED=true`, it throws and refuses to sign.

The application stores signing request metadata and signed payload references, not plaintext private keys, seed phrases, MPC shares, or HSM secrets.
