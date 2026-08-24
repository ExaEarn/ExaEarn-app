# ExaCard Web Product

The web app exposes ExaCard as a dedicated product surface from the ExaEarn ecosystem.

Implemented connected flows:

- Product discovery from `GET /api/cards/products`.
- Card issuance with `Idempotency-Key`.
- Card list and selected-card balance from ledger projection.
- Funding quote and funding request submission.
- Card unload back to funding wallet.
- Online control toggle, freeze, unfreeze, lost/stolen report, daily limit update.
- Provider-hosted secure-details token request.
- Card transactions and authorizations from provider webhooks.

The normal card response intentionally omits PAN, CVV, full card number, and raw provider secrets.

