# ExaCard Mobile Product

The React Native app now includes an authenticated ExaCard screen reachable from the dashboard feature grid.

Implemented mobile flows:

- Load ExaCard products and provider status.
- Issue a selected virtual card.
- Select an issued card and view projected card balance.
- Create a funding quote and submit funding with idempotency.
- Unload card funds to funding wallet.
- Toggle online controls and restrict international usage.
- View recent card transactions.

Mobile uses the existing `AuthContext.request` path and does not introduce a second auth or wallet system.

