# ExaEarn AgriTech Compliance and Security

Public investment uses the Phase 16 product/jurisdiction policy under `AGRITECH_INVESTMENT`, with a default deny decision. User identity, account state, KYC level and jurisdiction are evaluated server-side. Phase 18 risk decisions are evaluated before funds are reserved.

Admin operations reuse existing RBAC through `agri.manage`. Evidence review, state changes, milestone approval, maker-checker disbursement, reconciliation and refunds are authenticated operations. Client-provided user IDs, prices, returns, fees and verification state are not trusted.

Account closure is blocked only while unresolved investments, payouts, leases or farmer obligations exist.
