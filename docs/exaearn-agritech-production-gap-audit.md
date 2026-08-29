# ExaEarn AgriTech Production Gap Audit

## Existing implementation

The existing product already provided projects, shares, farmer applications, leases, produce reports, harvest jobs, rewards, web routes and basic admin visibility. It did not provide an authoritative financial investment flow: share allocation preceded canonical money settlement, harvest values were operator supplied, evidence could become verified from upload metadata, and legacy return calculations could fall back to PHP floats.

## Money-path findings

| Path | Previous behavior | Resolution |
| --- | --- | --- |
| Participation | Share row and investment record only | Canonical reservation, escrow settlement and allocation |
| Harvest | Operator-entered amount queued rewards | Approved revenue evidence and canonical settlement |
| Disbursement | No controlled escrow release | Milestone, maker-checker and escrow coverage |
| Refund | No canonical project refund workflow | Idempotent escrow refund and share release |
| Reconciliation | No product reconciliation | Findings for shares, ledger backing and payouts |
| Decimal math | Float fallback possible | `FinancialDecimal` only |

## Product and verification gaps

The old UI described tokenized land, liquid exits and fixed fees without corresponding legal, provider or pricing authority. These claims were removed. Project `economic_type`, `legal_status`, `verification_status`, and `public_funding_enabled` now independently gate behavior. Evidence submission remains `PENDING_REVIEW` until an authorized decision.

## Remaining external gaps

Legal product classification, land-title validation providers, farm inspection operations, insurance underwriting, custody/tokenization approval, and agricultural revenue verification partnerships remain external. Public investment and tokenization are disabled by default.
