# ExaEarn Affiliate Payouts

## Active Method

ExaPoints are the active affiliate payout method.

The payout workflow:

1. User has AVAILABLE commission.
2. User requests payout with idempotency key.
3. Payout batch is created.
4. Affiliate payout is recorded.
5. Available commissions are marked PAID.
6. ExaPointService credits points using a deterministic reference.

Duplicate payout requests with the same idempotency key return the original payout.

## Disabled Methods

Real-money, fiat, crypto and ExaToken affiliate payouts are disabled until operational setup is complete:
- payout rail
- compliance policy
- finance accounting mapping
- tax reporting policy
- treasury funding
- settlement controls

No direct wallet mutation is performed by affiliate payout code.
