# ExaEarn Support Agent Operations

Support agents reuse existing admin identities and RBAC. A supervisor grants support permissions, configures an agent profile, and the agent sends heartbeats while working.

## Activation Without Code

```text
create or activate admin/staff user
grant support live-chat permissions
create support agent profile
agent heartbeat ONLINE
enable live chat in Support settings
users can start chat
```

Presence states are `ONLINE`, `BUSY`, `AWAY`, and `OFFLINE`. Online presence expires if heartbeat stops.

Manual assignment and transfer preserve the same conversation identity and transcript.

