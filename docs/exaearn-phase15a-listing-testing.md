# ExaEarn Phase 15A Listing Testing

Listing test runs validate:

- Supported network exists.
- Contract/asset is registered.
- Deposits remain disabled before launch.
- Withdrawals remain disabled before launch.
- Market exists only in `PRE_LAUNCH`.
- No manual live price exists.
- Developer API discovery is ready.
- WebSocket discovery is ready.

Focused test coverage is in `backend/api-gateway/tests/Feature/Phase15AListingPortalTest.php`.
