# ExaEarn Notification Retention

User delete and clear operations now archive notifications by setting `status=archived` and `archived_at`.

Activity records remain immutable. Retention policy can later purge expired notification rows only after legal, compliance and audit constraints are satisfied.
