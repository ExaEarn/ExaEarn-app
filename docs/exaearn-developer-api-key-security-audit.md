# ExaEarn Developer API Key Security Audit

## Existing Architecture

The audit found one existing Developer credential system: `developer_api_keys`, normalized permissions, normalized IP rules, nonce records, HMAC middleware, project audit logs, Sandbox Explorer proxying, and matching TypeScript/Python SDK clients. It was retained.

| Capability | Prior classification | Finding |
|---|---|---|
| Project and environment binding | Partial | Keys carried a project and environment string, but runtime endpoint environment was not enforced |
| Key generation | Production ready | Framework randomness was already cryptographically secure |
| Secret storage | Production ready/partial | HMAC secret was encrypted and hash recorded, but encrypted fields were serializable |
| One-time display | Partial | Create returned plaintext once, but model redaction needed hardening |
| HMAC verifier | Production ready/partial | Constant-time comparison, timestamp, and nonce existed |
| Canonical signing | Unsafe | Python used milliseconds and a different canonical field order |
| Scope model | Partial | Flat allowlist duplicated product/risk semantics |
| IP allowlist | Unsafe | Unvalidated strings and IPv4-only CIDR matching |
| Lifecycle | Partial | Disable and in-place secret rotation existed; no explicit permanent revoke/enable/policy lifecycle |
| Realtime revocation | Unsafe | Realtime session tokens were not persisted or key-bound |
| Parent state | Partial | Project/workspace state checked; personal account and organization state needed explicit policy |
| Authenticated rate limiting | Partial | Outer route limiter was IP-oriented |
| Console | Missing | No consumer-grade API-key management surface |

## Security Weaknesses Closed

- Added immutable credential UUID lifecycle routes and explicit `created_by`, disable, revoke, and revoker metadata.
- Hid key hashes, encrypted key material, encrypted secrets, secret hashes, and passphrase hashes from serialization.
- Retained encrypted recoverable HMAC material because HMAC verification cannot use an irreversible secret hash; encryption keys remain external application configuration.
- Added one canonical scope registry with risk, environment, product status, approval, and IP policy metadata.
- Added server-side IPv4, IPv6, and CIDR validation and enforcement through Symfony `IpUtils`.
- Added runtime Sandbox/Production gateway binding through `DEVELOPER_API_RUNTIME_ENVIRONMENT`.
- Added deterministic RFC3986 query sorting and one shared signing fixture.
- Corrected Python signing field order and Unix timestamp unit.
- Added credential/environment/endpoint-class rate limiting and response metadata.
- Persisted hashed, expiring realtime session tokens and revoke them immediately when their key is disabled or revoked.
- Added security-failure audit events without credential secrets.
- Added transactional key-limit enforcement, scope/IP updates, and irreversible revocation.

## Canonical Signing Contract

```text
UPPERCASE_METHOD
NORMALIZED_PATH
RFC3986_SORTED_QUERY_PAIRS
UNIX_TIMESTAMP_SECONDS
UNIQUE_NONCE
SHA256_HEX(EXACT_RAW_BODY)
```

The signature is lowercase hexadecimal `HMAC-SHA256(secret, canonical_request)`. Duplicate query pairs are retained and sorted by decoded key and value. The body is not reserialized by the verifier.

## Authentication Order

1. Required headers and timestamp window.
2. Resolve active credential by hashed API key.
3. Project, workspace, account/organization, and project-environment state.
4. Runtime environment binding.
5. Passphrase and IP policy.
6. Decrypt HMAC secret and verify signature in constant time.
7. Persist nonce under a unique key constraint.
8. Enforce endpoint scopes.
9. Credential rate limit.
10. Execute and record `last_used_at`.

## Operational Boundary

Sandbox credentials remain available without KYC/KYB. Production key creation remains denied because every new Production environment is `not_activated`. Trusted proxy configuration must be verified during deployment so `Request::ip()` cannot be influenced by untrusted forwarded headers.
