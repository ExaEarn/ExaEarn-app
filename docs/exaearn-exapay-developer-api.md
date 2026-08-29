# ExaPay Developer API

ExaPay is exposed through the Phase 14 developer API permission model.

Scopes:

- `exapay.read`
- `exapay.write`
- `exapay.refunds`
- `exapay.manage`

Routes include:

- `GET /api/developer/v1/exapay/merchants`
- `GET /api/developer/v1/exapay/merchants/{merchantId}/overview`
- `POST /api/developer/v1/exapay/merchants/{merchantId}/payment-intents`
- `POST /api/developer/v1/exapay/payment-intents/{payIntent}/capture`
- `POST /api/developer/v1/exapay/merchants/{merchantId}/payment-links`
- `POST /api/developer/v1/exapay/merchants/{merchantId}/refunds`

Developer signing, timestamp, nonce, IP allowlist and scope enforcement remain inherited from Phase 14.
