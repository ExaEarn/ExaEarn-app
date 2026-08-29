# ExaPay Payment Links

Payment links are stored in `merchant_payment_links`.

Fields include:

- title
- description
- fixed or variable amount mode
- currency
- expiry
- maximum uses
- success/cancel URL
- status

Each payment link use creates a real `exaearn_pay_intents` record. Updating a payment link does not mutate completed payments.

Statuses:

```text
ACTIVE
PAUSED
EXPIRED
DISABLED
```
