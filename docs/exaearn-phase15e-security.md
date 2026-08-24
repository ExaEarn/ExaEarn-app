# ExaEarn Phase 15E Security

Controls:

- institutional authentication
- Phase 15B institutional ownership checks
- subaccount permission checks for bot creation
- admin `institutional.manage` authorization for approval/live-cycle controls
- no frontend exposure of API secrets
- no direct order-book writes
- no direct balance mutation
- no customer-fund use for ExaEarn-managed bots without explicit capital attribution
