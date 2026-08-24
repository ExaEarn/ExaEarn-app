# ExaEarn Phase 15A Listing Portal

The external listing portal lives in `apps/listing` as `@exaearn/listing`.

Applicants can:

- Create a listing organization.
- Save token listing drafts.
- Submit the application with an authorized declaration.
- Track application and integration status.
- Message ExaEarn listing operations for their own applications only.

The portal calls authenticated Laravel routes under `/api/listing/*`. It does not show live listings, prices, order books, or liquidity promises.
