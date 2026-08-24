# ExaCard Final Gap Audit

## Scope

This audit covers the ExaCard software completion gate after the initial card foundation was implemented.

## Closed Software Gaps

| Gap | Classification | Resolution |
| --- | --- | --- |
| Card activity was not exposed to users | SOFTWARE | Added authenticated transaction and authorization history APIs and web/mobile activity views. |
| Provider uncertain funding state was not explicit | SOFTWARE | Added sandbox `PROVIDER_UNKNOWN` behavior that keeps funds reserved without ledger debit until reconciliation/provider confirmation. |
| Chargebacks did not create reviewable disputes | SOFTWARE | Signed webhook processing now creates idempotent `card_disputes` records for chargeback events. |
| Card loss/compromise reporting was missing | SOFTWARE | Added user endpoint to block a card and audit lost/stolen reports. |
| Card termination lacked safety checks | SOFTWARE | Added termination endpoint blocked by card balance, open authorizations, pending funding/unloads, or open disputes. |
| Admin operations visibility was too narrow | SOFTWARE | Added admin endpoints for customers, transactions, funding/unloads, disputes, treasury, providers, and revenue. |
| Treasury low-balance state was generic | SOFTWARE | Provider balance shortfalls now report `REBALANCE_REQUIRED`. |
| Web card console was incomplete | SOFTWARE | Rebuilt ExaCard page around product selection, issue, fund, unload, controls, limits, secure details, and activity. |
| Mobile card flow was absent | SOFTWARE | Added mobile ExaCard service, screen, route, and dashboard tile. |

## Remaining External Dependencies

| Dependency | Classification | Status |
| --- | --- | --- |
| Real issuer/acquirer card provider credentials | EXTERNAL SERVICE | Required for live card issuance. Sandbox/fake adapter remains isolated from production mode. |
| Card program legal/compliance approval | LEGAL/REGULATORY | Required before public production activation. |
| BIN sponsorship/card network onboarding | EXTERNAL SERVICE | Required for real virtual/physical card products. |
| PCI scope attestation/provider-hosted sensitive details | LEGAL/REGULATORY | Sensitive PAN/CVV is not stored or returned by ExaEarn APIs. |

