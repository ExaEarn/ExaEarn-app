# ExaEarn Unified Activity Center

The Activity Center exposes two feeds:

- Notifications: attention items with unread/read/archive state.
- Activity: immutable account history sourced from authoritative audit/activity records.

Endpoints:

- `GET /api/activity-center`
- `GET /api/activity-center/notifications`
- `GET /api/activity-center/activity`

Activity supports category filtering for money, trading, earn, ecosystem, security and system.
