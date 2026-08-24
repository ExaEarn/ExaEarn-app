# ExaEarn Phase 14 Load Testing

## Scope

Phase 14 load validation covers the developer interface layer only. It does not create trades, balances, fills or ledger effects outside the existing ExaEarn product services.

## Completed Local Probes

- Developer realtime durable event probe: 1,000 ordered events on one private stream with replay from sequence 995.
- Webhook batch probe: 25 events fanned out to one healthy endpoint and one failing endpoint, covering delivered, retrying, dead-lettered and replay states.
- Product API routing probe: Futures, Margin, Staking, Copy Trading and ExaAI signed routes reject missing scopes and enter existing product validation/risk paths.

## Results

```text
Phase14DeveloperPlatformTest:
13 passed / 0 failed / 1102 assertions

1K developer realtime durable event probe:
PASS

Webhook batch delivery/retry/dead-letter probe:
PASS

Product API security/routing probe:
PASS
```

## 10K WebSocket Capacity

The current local PHP test environment does not run a real external WebSocket gateway with 10,000 concurrent socket connections. Phase 14 therefore does not claim a local 10K network-socket PASS.

```text
10K WEBSOCKET LOAD:
ENVIRONMENT BLOCKED
```

Production capacity validation should be run in a dedicated environment with the deployed developer gateway, Redis, queue workers and observability enabled.
