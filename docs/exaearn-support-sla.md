# ExaEarn Support SLA

SLA policies are stored in `support_sla_policies`.

Default targets are created by priority:

- `CRITICAL`: first response 15 minutes, resolution 4 hours
- `URGENT`: first response 30 minutes, resolution 8 hours
- `HIGH`: first response 2 hours, resolution 24 hours
- `NORMAL`: first response 4 hours, resolution 48 hours
- `LOW`: first response 12 hours, resolution 72 hours

The SLA evaluator reports `ON_TRACK`, `AT_RISK` and `BREACHED`. Breach escalation is software-supported through admin escalation and ticket event records.
