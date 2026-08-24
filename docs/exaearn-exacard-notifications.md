# ExaCard Notifications

## Purpose

ExaCard notifications use the existing ExaEarn `NotificationService`. They are user communication events, not financial source-of-truth records.

## Implemented User Notifications

- Card created
- Funding completed
- Funding failed
- Funding provider pending/unknown
- Purchase approved
- Purchase declined
- Refund received
- Card frozen
- Card active again
- Card blocked
- Card terminated
- Dispute updated

## Deduplication

Card notifications include a stable product reference in notification data. Before sending, ExaCard checks for an existing notification with the same user, type, and reference so duplicated provider webhook delivery does not create duplicate user messages.

## Failure Handling

Notification delivery is best-effort. If the notification provider or queue layer fails, ExaCard logs a warning and does not roll back an already valid card funding, unload, webhook capture, or ledger settlement.

## Privacy

Notifications never include PAN, CVV, raw provider secrets, webhook signatures, or unsafe card metadata. Purchase notifications may include merchant, amount, currency, status, and card last four digits where already available.

## Operations Alerts

ExaCard emits operations alerts through the existing SRE observability system for:

- Low provider balance
- Webhook signature or processing failure
- Card reconciliation breaks

Operations alerts are also mirrored into the durable `exacard.operations` realtime stream for admin/ops consoles.
