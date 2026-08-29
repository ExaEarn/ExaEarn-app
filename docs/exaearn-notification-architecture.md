# ExaEarn Notification Architecture

Financial and product systems commit authoritative state first. After the authoritative state exists, product code emits a registered notification event through `NotificationService::emit`.

The service validates the event key, applies preferences, enforces mandatory delivery, deduplicates by authoritative event/entity reference, creates an in-app notification, writes immutable delivery attempts, and queues email or push delivery where configured.

Notifications are attention items. Account Activity is a separate immutable feed generated from authoritative actions and audit services.
