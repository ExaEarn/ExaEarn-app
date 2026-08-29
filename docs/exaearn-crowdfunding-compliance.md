# ExaEarn Crowdfunding Compliance

Crowdfunding integrates with the Phase 16 policy layer.

## Backer Checks

Before pledging, the service calls `CompliancePolicyService::assertAllowed` with product `CROWDFUNDING`, action `PLEDGE`, classification and jurisdiction context.

## Creator Checks

Campaign approval/live transitions require an active creator with verified or approved verification state.

## Investment Gate

Investment crowdfunding remains an external legal/product dependency and is disabled by default.

