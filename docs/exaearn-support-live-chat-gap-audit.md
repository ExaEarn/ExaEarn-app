# ExaEarn Support Live Chat Gap Audit

## Scope

This audit covers the existing Support platform live-chat layer after the Level 3 ticketing foundation. It verifies chat persistence, configuration, agent operations, web/mobile access, admin controls, realtime recovery, and ticket fallback.

## Findings

| Area | Status | Notes |
| --- | --- | --- |
| Support ticketing foundation | READY | Existing SupportService, queues, SLA, attachments, KB and notifications are reused. |
| Chat persistence tables | READY | Existing `support_chats` and `support_chat_messages` are extended with conversation numbers, queues, assignment, lifecycle timestamps, message UUIDs, visibility and idempotency. |
| Public live-chat default | READY | Defaults keep `live_chat_enabled`, `public_chat_enabled`, `web_chat_enabled` and `mobile_chat_enabled` false. |
| Admin enable/disable | READY | Persisted DB settings can be changed through admin routes; no redeploy is required. |
| Operating hours | READY | Timezone, windows and holidays are configuration-backed. |
| Agent presence | READY | Admin identities are reused with `support_agent_profiles`; heartbeat expiry prevents stale staff from appearing online. |
| Queues and assignment | READY | Existing support queues are reused; auto and manual assignment respect queue and concurrency limits. |
| Message ordering/replay | READY | Messages are persisted with monotonic per-chat sequence; replay can recover missed messages. |
| Internal notes | READY | Internal notes are stored for admin replay and excluded from user replay. |
| Web support chat | READY | Web uses `/api/v1/support/chat/*` and shows ticket fallback when unavailable. |
| Mobile support chat | READY | Mobile uses the same availability/start/message endpoints. |
| Admin agent console | READY | Admin Support Center exposes settings, health, presence, conversations, replies and conversion actions. |
| Realtime | READY | Redis publish is best-effort; database replay is authoritative for reconnect/gap recovery. |
| Live staffing | OPERATIONAL SETUP REQUIRED | Staff must be hired/assigned but no source-code change is required. |
| Public live chat | DISABLED_BY_ADMIN | Correct safe production default until operations explicitly enable it. |

