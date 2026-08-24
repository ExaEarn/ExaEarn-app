# ExaEarn Phase 15B API Isolation

Phase 15B extends Phase 14 developer API keys with optional institutional scope:

- `institution_id`
- `subaccount_id`
- `rate_profile`

`DeveloperApiKeyService` validates institutional ownership and active subaccount status before issuing a scoped key. `DeveloperApiAuth` attaches the institutional context to the request and blocks client attempts to spoof a different `subaccount_id`.

The API gateway remains notification and command infrastructure only. Financial writes still pass through OMS, risk, reservation, settlement and ledger services.

Current enforcement:

- API key issuance requires active institution ownership.
- Subaccount-scoped keys cannot request another subaccount by ID.
- Request attributes expose `institution_id`, `institutional_subaccount_id`, and `api_rate_profile` to downstream controllers.

Remaining future hardening:

- Broader downstream product controllers should consume the request attributes to default every institutional write to the scoped subaccount when the route supports institutional trading.

