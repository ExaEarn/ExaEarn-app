# ExaEarn Support Live Chat Runbook

## Enable Live Chat

1. Confirm support staffing is available.
2. Create or activate admin staff users.
3. Grant support live-chat permissions.
4. Configure support agent profiles and queues.
5. Agents heartbeat as `ONLINE`.
6. Enable `live_chat_enabled`, `public_chat_enabled`, and the required channel flags.
7. Confirm health shows backend, assignment and online agents healthy.

## Disable Live Chat

Turn off `public_chat_enabled` or channel-specific settings. Ticket support remains available.

## No Agents Online

Users see fallback ticket messaging. Supervisors should confirm heartbeats and queue staffing.

## Realtime Failure

Messages remain persisted. Ask users or agents to refresh; clients replay from the last sequence.

## Queue Full

Increase staffing/capacity or keep fallback ticket intake active. Do not fake an online agent.

