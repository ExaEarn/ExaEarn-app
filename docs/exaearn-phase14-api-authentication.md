# ExaEarn Phase 14 API Authentication

## Headers

Private developer API requests require:

```text
EXA-API-KEY
EXA-API-TIMESTAMP
EXA-API-NONCE
EXA-API-SIGNATURE
EXA-API-PASSPHRASE
```

`EXA-API-PASSPHRASE` is required only when the key was created with a passphrase.

## Signature

Sign this canonical payload with HMAC-SHA256 using the API secret:

```text
METHOD
PATH
QUERY_STRING
TIMESTAMP
NONCE
BODY_SHA256
```

Example:

```text
GET
/api/developer/v1/wallet/balances

1787374652
exa_nonce_4b3f...
e3b0c44298fc1c149afbf4c8996fb924...
```

Rules:

- `METHOD` is uppercase.
- `PATH` includes `/api/developer/v1/...`.
- `QUERY_STRING` is the raw query string without leading `?`.
- `TIMESTAMP` is Unix seconds.
- `NONCE` must be unique per API key and is stored server-side to prevent replay.
- `BODY_SHA256` is the SHA-256 hex digest of the exact transmitted request body.
- Empty body hash is SHA-256 of the empty string.

## Failure Modes

| Code | Meaning |
| --- | --- |
| `INVALID_API_KEY` | Missing, inactive, expired or unknown key. |
| `TIMESTAMP_EXPIRED` | Timestamp is outside the allowed replay window. |
| `INVALID_PASSPHRASE` | Passphrase does not match key configuration. |
| `IP_NOT_ALLOWED` | Key IP whitelist does not include request IP. |
| `INVALID_SIGNATURE` | Signature does not match canonical request. |
| `NONCE_REPLAYED` | Nonce was already used with the same key. |
| `PERMISSION_DENIED` | Key lacks the endpoint permission. |

Every response includes `X-Exa-Request-Id` for support and audit correlation.
