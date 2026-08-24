# ExaEarn Phase 15A Listing State Machine

Application approval is separated from exchange launch.

## Application States

`DRAFT -> SUBMITTED -> REVIEW STATES -> FINAL_REVIEW -> APPROVED`

Direct `SUBMITTED -> LIVE` is rejected because `LIVE` is an integration state and requires configuration, tests, liquidity readiness, final approval and scheduling.

## Integration States

`NOT_STARTED -> INTEGRATION -> ASSET_CONFIGURATION -> NETWORK_CONFIGURATION -> MARKET_CREATED -> TESTING -> READY_FOR_LISTING -> SCHEDULED -> PRE_LAUNCH -> LIVE`

Exceptional states: `LAUNCH_BLOCKED`, `PAUSED`, `SUSPENDED`, `DELISTING`, `DELISTED`.

## Safety

Invalid transitions fail server-side through lifecycle methods. Launch checks revalidate asset, network, contract, tests, liquidity and market state immediately before trading opens.

