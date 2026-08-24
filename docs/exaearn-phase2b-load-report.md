# ExaEarn Phase 2B Load Report

Phase 2B includes a local spot-engine load harness.

## Implemented Components

- Model: `App\Models\SpotEngineLoadRun`
- Command: `php artisan spot:load-harness {--orders=100} {--market=LOAD/USDT}`

## Latest PostgreSQL Run

Command:

```bash
php artisan spot:load-harness --orders=50 --market=LOAD2B/USDT
```

Result:

- Run ID: `31473624-b503-4336-b6a8-ef655512dafb`
- Orders submitted: `50`
- Orders accepted: `50`
- Trades created: `25`
- Duration: `5081.914ms`
- P50 latency: `123.543ms`
- P95 latency: `196.837ms`
- P99 latency: `220.872ms`
- Error count: `0`
- Replay last sequence: `50`
- Replay checksum: `f24584fe4984f7d0e9c2b2015aeeeeb6ed949e2fe8dc09841a945972250f5e3b`

## Scope

This is a correctness and local stress harness, not a substitute for external production load testing. Before production traffic, run a staged environment load test with realistic market counts, websocket fanout, database latency and settlement backlog.

