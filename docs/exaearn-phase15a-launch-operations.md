# ExaEarn Phase 15A Launch Operations

Launch is controlled by admin `v1` listing-center APIs.

Launch requirements:

- Application approved.
- Asset configuration exists.
- At least one market configuration exists.
- Latest listing tests pass.
- Market configurations are still `PRE_LAUNCH`.
- Final scheduling uses maker-checker separation.

Emergency controls can pause deposits, withdrawals, trading, or halt all listing activity for the application. These controls create listing audit records.
