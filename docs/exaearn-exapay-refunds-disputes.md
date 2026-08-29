# ExaPay Refunds And Disputes

Refunds use `PaymentRefundService` and ledger reversal. Duplicate refund requests for the same original ledger reference return the existing refund.

Supported refund states:

```text
CREATED
COMPLETED
```

Disputes use `payment_disputes` with merchant metadata. Merchant webhook events are emitted for dispute creation.

Provider chargeback network operations and evidence submission workflows remain external/operational dependencies.
