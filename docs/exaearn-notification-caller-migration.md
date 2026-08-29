# ExaEarn Notification Caller Migration

## Status

Product notification callers now have a canonical path:

```text
Product event
  -> NotificationService::emit(event_key, variables, entity_reference)
  -> NotificationEventDefinition
  -> NotificationTemplateService
  -> Notification + NotificationLog
  -> Unified Activity Center
```

## Migrated Callers

| Product | Caller | Canonical event |
|---|---|---|
| ExaCard | `Cards/CardNotificationService` | `exacard.*` registered event keys |
| Giftcards | `GiftCardDeliveryService` | `giftcard.delivery.ready` |
| Compliance/KYC | `Kyc/NotifyJob` | `compliance.kyc.action_required` |
| ExaAI | `ExaAiService` | `exaai.subscription.activated`, `exaai.session.started` |
| Admin operations | `AdminPlatformController::sendNotification` | registered event key or `admin.broadcast.*` only |

## Compatibility Path

`NotificationService::create(...)` remains available for legacy compatibility and safe admin broadcast delivery. New product/system/security/transaction notifications should use `emit(...)` with a registered event key.

The admin send route no longer accepts arbitrary event identities unless they use the `admin.broadcast.*` namespace. Registered financial/security/product notifications are rendered and logged through `emit(...)`.

## Remaining Work

Future product teams should migrate any newly discovered direct notification writes to `emit(...)`. This is migration hygiene, not a current software gate blocker for the unified notification platform.
