# ExaEarn Phase 11 Pre-Implementation Audit

## Existing Components

| Component | Status | Decision | Notes |
| --- | --- | --- | --- |
| `P2PController` | Working API facade | KEEP | Existing web/mobile routes continue to use it. Versioned aliases were added instead of replacing it. |
| `P2PService` | Broad service with ads, orders, escrow, chat, disputes | HARDEN | Retained as compatibility facade; escrow and risk are now delegated to P2P domain services. |
| `p2p_ads` | Existing advertisement table | KEEP | Supports buy/sell ads, asset, fiat, price, limits, payment methods and status. |
| `p2p_trades` | Existing order table | HARDEN | Additive Phase 11 columns store canonical reservation and ledger references. |
| `p2p_payment_methods` | Existing user payment account table | CONSOLIDATE | Kept as current P2P payment-account registry; should be bridged deeper into Phase 10 verified bank accounts over time. |
| `p2p_messages` | Existing order chat | KEEP | Order-scoped and moderated through queued job. |
| `p2p_disputes` | Existing dispute table | HARDEN | Resolution now settles through canonical escrow actions. |
| `p2p_ratings` | Existing feedback table | KEEP | Reputation snapshots now consume this history. |
| Legacy wallet freeze/unfreeze escrow | Worked but not canonical | REPLACE | New orders use `ReservationService` and `SettlementService`. Historical columns remain for old records. |
| Payment proof | Existing private local storage | HARDEN | Evidence records now include SHA-256 hash, uploader, MIME and size metadata. |
| Buyer “paid” button | Existing state transition | KEEP WITH WARNING | It remains a state signal only; it does not release escrow. |

## Main Risks Found

- P2P escrow used legacy transaction and wallet freeze/unfreeze paths instead of canonical reservations.
- P2P decimal helpers silently fell back to PHP floats when BCMath was unavailable.
- Payment proof was stored in trade metadata only, without immutable evidence records.
- Risk decisions were not journaled as P2P risk events.
- Merchant reputation was calculated inline for ad display only, not versioned or snapshotted.
- Admin P2P visibility existed mainly through dispute review, not full operational readiness and reconciliation.

## Implemented Hardening

- Canonical escrow reservation at order creation.
- Idempotent ledger settlement at escrow release.
- Reservation release on cancellation/expiration.
- Phase 11 order event journal.
- Payment evidence table with file hash.
- P2P risk-event table and risk engine.
- P2P reputation snapshots.
- P2P escrow reconciliation runs.
- Admin `/api/admin/v1/p2p/*` read/control-plane endpoints without arbitrary balance-edit operations.
