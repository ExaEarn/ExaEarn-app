# ExaSkills Subscriptions

ExaSkills subscriptions are configured in `config/exaskills.php`.

Supported software states are `PENDING`, `ACTIVE`, `PAST_DUE`, `PAUSED`, `CANCELLED` and `EXPIRED`. Activation and renewal require `Idempotency-Key` and post ledger-backed subscription revenue when the configured plan price is greater than zero.

Subscription access is an entitlement layer only. Expiry removes subscription-based access but does not remove permanent course purchases or business assignments.

