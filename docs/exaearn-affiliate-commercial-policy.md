# ExaEarn Affiliate Commercial Policy

## Principles

- Commission is never based on deposits alone.
- Commission is never based on cancelled, failed, sandbox or unsettled activity.
- Commission is paid from approved marketing/revenue-share policy, not by increasing customer fees implicitly.
- ExaToken distribution remains disabled.

## Commissionable Registry

The central registry is `backend/api-gateway/config/affiliate.php`.

Currently enabled:
- `EXAAI:SUBSCRIPTION_PURCHASE`

Currently configured but disabled:
- Spot fees
- Futures fees
- Convert fees
- ExaPay merchant fees
- Giftcard purchase margin

Each disabled product must provide an authoritative settled fee/revenue event before activation.

## Precedence

RewardPolicyEngine has precedence when an active `AFFILIATE:{PRODUCT}_{EVENT}` rule exists. If no policy rule is active, AffiliateTier fallback is used so the product remains operational with audited tier settings.
