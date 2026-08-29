# ExaEarn Crowdfunding Classification

Crowdfunding supports explicit product classifications.

## Software-Enabled Non-Investment Classes

- `PROJECT_SUPPORT`
- `DONATION`
- `REWARD`
- `PREORDER`
- `COMMUNITY_GRANT`

These can enter the normal review lifecycle when enabled by operations policy.

## Investment Classes

- `EQUITY`
- `DEBT`
- `REVENUE_SHARE`
- `TOKEN_SALE`
- `YIELD_PRODUCT`

Investment classes are blocked by default through `crowdfunding.investment_campaigns_enabled=false`. Public activation also fails closed until external legal/product approval exists.

## Public Truth

Do not label equity, debt, yield, revenue share or token-sale fundraising as ordinary campaign support.

