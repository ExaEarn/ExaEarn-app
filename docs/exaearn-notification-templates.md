# ExaEarn Notification Templates

The schema now supports versioned notification templates through `notification_templates` and event definitions reference `template_key` and `template_version`.

The current delivery path uses sanitized event-provided title/message variables as a compatibility bridge. Product migration should move high-volume and compliance-sensitive copy into stored versioned templates before public operational scale.
