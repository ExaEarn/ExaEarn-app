# ExaPay Hosted Checkout

Hosted checkout is backed by a payment intent.

The backend creates an unguessable checkout token and stores only its SHA-256 hash in `exaearn_pay_intents.checkout_token_hash`.

Checkout reads:

```text
GET /api/exapay/checkout/{token}
```

The browser cannot change amount, currency, merchant, status or fees after intent creation. The payment intent remains authoritative.

Supported software behavior:

- short-lived checkout tokens
- merchant-bound payment display
- amount/currency/description display
- accepted payment method metadata
- no internal provider-name leakage required
