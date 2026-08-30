# ExaEarn Developer Platform Level D Launch Plan

Date: 2026-08-31  
Status: inactive - Level D is not authorized

## Authorization boundary

There is no executable Level D launch plan for the current candidate. Deployment and operations personnel must not create Production credentials, approve individual users, enable Production webhooks, enable wallet withdrawal, or enable organization Production Access from this document.

The launch plan may become active only after the independent Level D audit is reissued with `FINAL LEVEL D VERDICT: AUTHORIZED` or `CONDITIONALLY AUTHORIZED`, all stated P1 conditions are evidenced, and an immutable release candidate is identified.

## Mandatory preserved controls

- Individual developers only; organization Production Access remains backend-blocked.
- Manual named allowlist and capability-by-capability approval.
- Wallet withdrawal remains blocked.
- Production webhooks remain disabled unless a later audit explicitly authorizes verified egress.
- Initial product boundary is Spot read/trade and Wallet read only, with low request and financial exposure limits.
- Futures, Margin, Earn/Staking, ExaPay, Copy Trading, and ExaAI remain unavailable until separately approved.
- Immediate credential revocation, project suspension, Production Access suspension, and realtime disconnection remain available to the operator.
- Any failed readiness, reconciliation, security, compliance, provider, or monitoring gate stops onboarding and new risk.

## Activation sequence after authorization

1. Record the approved commit, images, SBOM, scan artifacts, migration report, environment/config hashes, rollback artifact, and approval ticket.
2. Validate trusted proxy, TLS/DNS/WAF, database, Redis, authority, queue, scheduler, monitoring, alerting, backup, restore, and rollback evidence in the target environment.
3. Confirm individual KYC/jurisdiction/product eligibility and the signed Production Beta agreement for each named participant.
4. Create the smallest approved cohort and scopes; apply conservative request, order, exposure, and loss limits.
5. Start read-only access, verify logs/alerts/revocation, then enable explicitly approved Spot writes one participant at a time.
6. Monitor continuously with a named operator and incident commander; stop onboarding on any unresolved anomaly.
7. Reconcile API activity, orders, fills, ledger effects, access decisions, and security events after each rollout step.
8. Roll back or suspend immediately if any authorization condition, SLO, security control, provider, compliance, or financial invariant fails.

## Explicit exclusions

This plan does not authorize public GA, organizations, withdrawals, unrestricted webhooks, unrestricted product scopes, automatic cohort expansion, or legal/compliance approval by software assertion.
