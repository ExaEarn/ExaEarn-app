# ExaPay Risk

Merchant risk state is stored separately from KYB state.

Merchant capture is blocked when:

- merchant is not `ACTIVE`
- KYB is not `APPROVED`
- risk status is `RESTRICTED`

Risk signals may be stored in `merchant_risk_signals` for review.

Transaction monitoring should evaluate velocity, refund/dispute rates, suspicious payer concentration, prohibited business categories and settlement attempts. The software creates review signals; it does not label activity as a crime automatically.
