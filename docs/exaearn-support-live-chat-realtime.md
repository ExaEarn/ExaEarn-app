# ExaEarn Support Live Chat Realtime

The live-chat realtime contract is persistence-first.

## Message Lifecycle

```text
validate request
persist message
assign sequence
publish best-effort realtime event
replay from DB when needed
```

Each persisted message includes:

- `message_uuid`
- `chat_id`
- `sequence`
- `sender_type`
- `message_type`
- `visibility`
- `idempotency_key`
- timestamps

User replay excludes internal notes. Admin replay includes internal notes for authorized operations.

## Gap Recovery

Clients track the last received sequence and call replay with `after_sequence`. Missing messages are recovered from `support_chat_messages`.

