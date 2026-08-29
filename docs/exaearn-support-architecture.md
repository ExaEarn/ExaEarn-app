# ExaEarn Support Architecture

Support is an orchestration layer:

```text
User or product event
  -> Support ticket
  -> Queue and SLA policy
  -> Agent workspace
  -> Escalation to product, finance, security or compliance
  -> Resolution and audit
  -> Notification
```

Support reuses existing ExaEarn authentication, admin RBAC, notifications, product dispute models and audit infrastructure. Product systems remain authoritative for P2P appeals, ExaCard disputes, ExaPay refunds/chargebacks, Giftcard reviews, staking operations, security cases and compliance cases.

Support never provides a generic balance adjustment path.
