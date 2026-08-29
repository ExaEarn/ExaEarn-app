# ExaEarn Notification Delivery

Delivery attempts are tracked in `notification_logs`.

Each attempt can record event id, channel, provider, recipient, attempt number, status, provider message id, queued/sent/delivered/failed timestamps, safe error code and template version.

Delivery failures must not roll back product state. Retrying notification delivery must not recreate economic effects.
