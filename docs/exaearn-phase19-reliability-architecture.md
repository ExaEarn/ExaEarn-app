# ExaEarn Phase 19 Reliability Architecture

Phase 19 adds a reliability/SRE layer around the existing ExaEarn production architecture.

## Flow

1. Services register in `sre_service_registry`.
2. Dependency checks write immutable health observations to `sre_dependency_checks`.
3. Queue and worker supervisors record operational state.
4. `ReliabilityOperationsService` evaluates liveness, readiness, dependency health and business readiness.
5. Recovery actions require maker-checker approval and execute only into safe mode after finance and security prechecks.
6. Finance reconciliation and security readiness remain mandatory resume gates.

## New Software

- `SreServiceRegistry`
- `SreDependencyHealthService`
- `SreQueueReliabilityService`
- `SreWorkerSupervisorService`
- `SreBackupService`
- `SreRecoveryService`
- `ReliabilityOperationsService`
- `ProductionConfigValidationService`
- `MarketDataFailoverService`
- `RpcFailoverService`
- `Admin\ReliabilityOperationsController`

## Source of Truth

Phase 19 does not create a new financial source of truth. Ledger, reservations, settlement, OMS, custody, fiat, finance and security systems remain authoritative.

