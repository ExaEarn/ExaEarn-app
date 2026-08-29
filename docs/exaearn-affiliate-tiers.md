# ExaEarn Affiliate Tiers

Affiliate tiers are stored in `affiliate_tiers`.

Supported attributes:
- code
- name
- commission rate in basis points
- monthly cap
- minimum payout
- payout frequency
- eligible products
- qualification rules
- status

Default tier:
- `STANDARD`
- ExaPoint payout
- commission rate from `AFFILIATE_DEFAULT_COMMISSION_BPS`

Tier changes should be handled through admin operations with reason and audit logging. High-impact commercial changes should use maker-checker policy before public expansion.
