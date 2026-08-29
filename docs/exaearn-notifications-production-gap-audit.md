# ExaEarn Notifications Production Gap Audit

## Current State

The repository already had a shared `Notification` model, `NotificationLog`, `NotificationService`, in-app/email/push jobs, user notification APIs, device-token APIs, admin send/list paths, and product callers from ExaCard, ExaAI, Giftcards and other modules.

## Gaps Closed

- Added a canonical event registry for product notification events.
- Added preference storage for user-configurable scopes while preserving mandatory security/financial delivery.
- Converted destructive user notification delete/clear operations into archive-first behavior.
- Added structured delivery-attempt fields to notification logs.
- Added provider-health, event-definition and template read models.
- Added a unified Activity Center service/API that keeps Notifications separate from immutable account Activity.
- Added deduplication by recipient, event key and authoritative event/entity reference.

## Remaining Gaps

- Several product services still use legacy `create(...)` calls and should migrate gradually to `emit(...)` with registered event keys.
- Email and push provider health are software-modeled but still depend on real provider operations and credentials.
- Mobile notification center parity was not found in the inspected repository and remains a product integration follow-up.
