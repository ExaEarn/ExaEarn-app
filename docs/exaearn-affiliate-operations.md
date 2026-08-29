# ExaEarn Affiliate Operations

Admin routes:

```text
GET  /api/admin/v1/affiliate/overview
GET  /api/admin/v1/affiliate/commissions
GET  /api/admin/v1/affiliate/payouts
GET  /api/admin/v1/affiliate/clawbacks
GET  /api/admin/v1/affiliate/tiers
POST /api/admin/v1/affiliate/tiers
POST /api/admin/v1/affiliate/reconciliation
GET  /api/admin/v1/affiliate/incidents
```

User routes:

```text
GET  /api/affiliate/overview
GET  /api/affiliate/referrals
GET  /api/affiliate/earnings
GET  /api/affiliate/tools
GET  /api/affiliate/payouts
POST /api/affiliate/payouts
```

Operational boundaries:
- ExaToken distribution remains disabled.
- Cash/crypto payouts remain operational setup required.
- Suspicious commissions are held, not automatically confiscated.
- Admin actions run through existing admin security/audit middleware.
