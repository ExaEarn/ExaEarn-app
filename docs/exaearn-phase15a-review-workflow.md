# ExaEarn Phase 15A Review Workflow

Review is separated from launch.

Application statuses include:

- `DRAFT`
- `SUBMITTED`
- `COMPLIANCE_REVIEW`
- `TECHNICAL_REVIEW`
- `SECURITY_REVIEW`
- `LIQUIDITY_REVIEW`
- `FINAL_REVIEW`
- `APPROVED`

Approval requires compliance, technical, security, and liquidity reviews to pass. A maker can recommend approval, but a different authorized admin must approve the application.

Approval only changes the application to integration. It does not activate deposits, withdrawals, trading, balances, prices, or market data.
