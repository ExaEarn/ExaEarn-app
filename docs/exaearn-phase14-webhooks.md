# ExaEarn Phase 14 Webhooks

## Status

Developer webhook registration, secret rotation, signed delivery, retry, dead letter and replay are implemented.

## Supported Events

Configured in `config/developer_api.php`:

- `order.filled`
- `order.cancelled`
- `deposit.completed`
- `withdrawal.completed`
- `withdrawal.failed`
- `transfer.completed`
- `copy.event`
- `exaai.event`

High-frequency raw market data is intentionally not sent by webhook.

## Delivery Lifecycle

```text
PENDING
DELIVERING
DELIVERED
RETRYING
DEAD_LETTERED
```

Retries use exponential backoff and are capped by `developer_api.webhooks.max_attempts`.

## Signing

Each delivery includes:

```text
X-ExaEarn-Event-Id
X-ExaEarn-Timestamp
X-ExaEarn-Signature
```

Signature:

```text
hmac_sha256(webhook_secret, timestamp + "." + raw_body)
```

Webhook secrets are separate from API key secrets.

## Replay

Webhook replay creates a new delivery row with the same stable `event_id`, allowing clients to deduplicate. Replay never recreates ExaEarn financial events.

## Production Security

Production webhook URLs must use HTTPS. Payloads are safe external payloads and must not include internal secrets, private keys or unrestricted financial internals.
# Final Delivery Validation

Webhook delivery supports signed payloads, retry, dead letter and replay with stable event IDs.

Local verification delivered a 25-event batch to a healthy endpoint, retried a failing endpoint, advanced failed deliveries to `DEAD_LETTERED` after the configured attempt limit and replayed an existing event without creating a new financial event.
