# ExaEarn Phase 15A Contract Validation

Contract validation is persisted in `listing_contract_validations`.

The software validates:

- network registration
- EVM contract address format where applicable
- decimals range
- submitted metadata consistency
- risk flags such as upgradeable, proxy, pausable, blacklist capability, transfer restriction, fee-on-transfer, mintable, freeze authority, owner privileges and unusual behavior

Real chain-native RPC validation remains operational setup when provider credentials are unavailable. The system records that truthfully instead of faking verification.

