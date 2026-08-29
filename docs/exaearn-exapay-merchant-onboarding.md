# ExaPay Merchant Onboarding

Merchants apply through `/api/exapay/merchants`.

Software states:

```text
APPLIED
UNDER_REVIEW
NEEDS_INFORMATION
APPROVED
ACTIVE
RESTRICTED
SUSPENDED
REJECTED
CLOSED
```

Activation requires:

- merchant owner
- business name/country/type
- settlement currency
- KYB approval
- non-restricted risk state

Admin approval is available through `/api/admin/exapay/merchants/{merchantId}/approve` and is audited by existing admin middleware.

External KYB document verification, prohibited-business assessment and production legal review remain operational dependencies.
