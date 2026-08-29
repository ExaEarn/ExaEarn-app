# ExaEarn Personalized Content Architecture

## Pipeline

`Admin + allowlisted product events + trusted adapters -> normalized content -> publication window -> Phase 16 eligibility -> deterministic ranking -> dashboard/For You -> interactions and analytics`

## Data model

`personalized_contents` stores normalized copy, provenance, safe route keys, product/entity references, targeting, KYC floor, publication window, priority, personalization weight, and frequency cap. `personalized_content_interactions` stores idempotent impression, click, dismissal, and save events by user and surface.

The existing crowdfunding `campaigns` table and notification tables remain independent and authoritative for their domains.

## Eligibility and ranking

Eligibility is evaluated before ranking. Country/region targeting and minimum KYC are checked first. A related regulated product is then evaluated through `CompliancePolicyService` with `DISCOVER`; non-ALLOW decisions are excluded.

Ranking is explainable and configured in `config/personalized_content.php`: primary/secondary interest matches, selected product affinity, watchlist asset affinity, Lite/Pro match, freshness, editorial priority, and personalization weight. Personalization never hides navigation or grants product access.

## Automation

`ProductEventContentService` accepts only event names in the explicit registry. Durable source event IDs produce unique idempotency keys. Connected sources:

- Executed listing `ANNOUNCEMENT` -> `market.listing.activated`.
- Staking product transition into `active` -> `earn.product.activated`.

Deposit, withdrawal, login, order fill, and other account events remain notifications/activity. They are intentionally not discovery cards.

## External content

`TrustedExternalContentAdapter` and `ExternalContentIngestionService` provide normalization, safe-route validation, provenance, bounded priority, expiry, and idempotency. No provider is activated because the repository has no verified news-content agreement/feed. Admin and system content remain fully functional without it.

## APIs

- `GET /api/personalized-content/dashboard`
- `GET /api/personalized-content/feed?page=1&type=...`
- `POST /api/personalized-content/{id}/{impression|click|dismiss|save}`
- Admin CRUD/lifecycle: `/api/admin/personalized-content`
- Admin event ingestion: `/api/admin/personalized-content/events/ingest`

CTA values are route keys from an allowlist, never arbitrary URLs or executable HTML.

## Delivery guarantees

Only published content, or scheduled content whose publish time has arrived, is eligible. Expired, paused, archived, dismissed, frequency-capped, region-ineligible, KYC-ineligible, and compliance-ineligible records are removed before ranking. Failure returns no content and the dashboard hides the surface without affecting financial functions.
