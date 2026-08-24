# ExaEarn Phase 15B Team RBAC

Institutional team access is separate from platform admin access.

Institutional roles store permission arrays such as:

- `VIEW_REPORTS`
- `MANAGE_TEAM`
- `MANAGE_SUBACCOUNTS`
- `INTERNAL_TRANSFER`
- `APPROVE_TRANSFER`
- `MANAGE_API_KEYS`

Subaccount-scoped permissions are stored in `institutional_member_subaccount_permissions`. This lets an owner give one operator permission to transfer from a treasury desk without granting broad access across every desk.

The master account owner receives the initial `OWNER` role during activation. Admin operators cannot activate an institution they just recommended, preserving maker-checker separation.

