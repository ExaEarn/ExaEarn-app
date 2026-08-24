# ExaEarn Phase 15F Integration Architecture

Phase 15F introduces a cross-module operations layer:

```text
Listing Center
    -> Market launch readiness
    -> Liquidity requirement
    -> Market-maker assignment
    -> Capital readiness
    -> Bot readiness
    -> No unsafe auto-launch

Institutional Accounts
    -> Subaccount isolation
    -> API key ownership/status
    -> Treasury/customer/MM fund boundaries

Market Maker + Bots
    -> Assignment active
    -> API key active
    -> Risk gate
    -> Mass cancel / pause

OTC
    -> Market enablement
    -> Settlement ledger checks

Phase 15 Operations
    -> Overview
    -> Readiness
    -> Risk overview
    -> Reconciliation
    -> Emergency controls
```

The implementation is intentionally thin. It orchestrates existing Phase 15A-E services and canonical financial infrastructure instead of duplicating exchange logic.
