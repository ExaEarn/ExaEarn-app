# ExaEarn Support Live Chat Architecture

Live chat is an operational layer on top of the existing Support platform. It does not replace tickets, product disputes, compliance, security, finance, or any product-authoritative workflow.

## Flow

1. User requests availability through `/api/v1/support/chat/availability`.
2. `SupportLiveChatService` evaluates persisted settings, channel enablement, maintenance, operating hours, queue capacity and agent presence.
3. If unavailable, the frontend shows ticket fallback.
4. If available, chat creation persists a `support_chats` row and initial system messages.
5. Auto assignment selects an online support agent profile with capacity.
6. Messages are persisted first, assigned a per-chat sequence, then published to Redis best-effort.
7. Reconnect uses replay from the database, not transient socket state.
8. Agents may end chats or convert them to tickets while preserving the transcript context.

## Financial Boundary

Live chat agents cannot mutate balances, ledgers, reservations, withdrawals, card authorizations, disputes or product outcomes. They can coordinate and escalate into authoritative product workflows only.

