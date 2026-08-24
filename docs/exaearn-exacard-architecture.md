# ExaCard Architecture

ExaCard is implemented as an orchestration layer over ExaEarn's canonical financial core.

## Flow

```text
User eligible funds
  -> funding quote
  -> ReservationService hold
  -> card provider funding request
  -> provider confirmation
  -> CardSettlementService
  -> LedgerService double-entry settlement
  -> BalanceProjectionService display
  -> FinanceAccountingService journal event
```

## Backend Components

- Models: `CardCustomer`, `Card`, `CardFundingQuote`, `CardFundingRequest`, `CardUnloadRequest`, `CardTransaction`, `CardAuthorization`, `CardWebhookEvent`, `CardDispute`, `CardProviderBalance`, `CardReconciliationRun`, `CardAuditLog`, `CardOrder`.
- Provider abstraction: `CardProviderInterface`.
- Sandbox adapter: `FakeCardProvider`.
- Provider registry: `CardProviderRegistry`.
- Eligibility: `CardEligibilityService`.
- Quotes: `CardQuoteService`.
- Settlement: `CardSettlementService`.
- Main orchestration: `CardService`.
- Treasury: `CardTreasuryService`.
- Reconciliation: `CardReconciliationService`.

## Public/User APIs

- `GET /api/cards/products`
- `GET /api/cards`
- `POST /api/cards`
- `GET /api/cards/{cardUuid}`
- `POST /api/cards/{cardUuid}/funding-quotes`
- `POST /api/cards/funding-requests`
- `POST /api/cards/{cardUuid}/unload`
- `POST /api/cards/{cardUuid}/freeze`
- `POST /api/cards/{cardUuid}/unfreeze`
- `PUT /api/cards/{cardUuid}/controls`
- `PUT /api/cards/{cardUuid}/limits`
- `POST /api/cards/{cardUuid}/details-token`
- `POST /api/webhooks/cards/{provider}`

## Admin APIs

- `GET /api/admin/v1/exacard/overview`
- `GET /api/admin/v1/exacard/cards`
- `POST /api/admin/v1/exacard/provider-balances`
- `POST /api/admin/v1/exacard/reconciliation-runs`
- `GET /api/admin/v1/exacard/audit-logs`

## Financial Safety

Funding debits are not final until provider confirmation. Provider failure releases the reservation and no card ledger credit is posted. Unknown/pending provider outcomes remain non-final for reconciliation.

Card funds are represented by canonical ledger account type `exacard`; source funds remain `funding`. Spending provider events are persisted as card transaction records and must be reconciled before any final card-account settlement expansion.

## Production Provider Policy

The sandbox provider is enabled for development/testing only. Real card issuance requires:

- a live provider adapter,
- configured provider credentials,
- provider webhook signing secret,
- production issuance flag enabled,
- PCI/provider security review,
- card-program compliance approval.

