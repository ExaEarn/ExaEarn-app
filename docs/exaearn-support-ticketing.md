# ExaEarn Support Ticketing

## Tables

- `support_tickets`
- `support_ticket_messages`
- `support_ticket_attachments`
- `support_ticket_events`
- `support_queues`
- `support_sla_policies`
- `support_escalations`

## States

`OPEN`, `TRIAGED`, `ASSIGNED`, `IN_PROGRESS`, `WAITING_FOR_USER`, `WAITING_FOR_INTERNAL`, `ESCALATED`, `RESOLVED`, `CLOSED`, `REOPENED`, `CANCELLED`.

Transitions are validated server-side. Closed tickets reopen through `REOPENED`; prior resolution history remains intact.

## User APIs

- `POST /api/v1/support/tickets`
- `GET /api/v1/support/tickets`
- `GET /api/v1/support/tickets/{ticket}`
- `POST /api/v1/support/tickets/{ticket}/messages`
- `POST /api/v1/support/tickets/{ticket}/attachments`
- `POST /api/v1/support/tickets/{ticket}/close`
- `POST /api/v1/support/tickets/{ticket}/reopen`

Ticket creation is idempotency-key aware.
