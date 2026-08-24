# ExaEarn Phase 19 Provider Failover

Provider failover covers blockchain RPCs, payment providers and external venues.

`RpcFailoverService` rejects wrong-chain providers, skips unhealthy or lagged providers and returns `PAUSE` when no valid provider remains.

External providers still require production credentials, provider redundancy and operational monitoring outside this repository.

