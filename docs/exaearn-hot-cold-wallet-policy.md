# ExaEarn Hot/Cold Wallet Policy

Custody wallets are classified independently from user ledger ownership.

Supported classifications:

- `USER_DEPOSIT`
- `HOT`
- `WARM`
- `COLD`
- `TREASURY`
- `NETWORK_FEE`
- `STAKING`
- `EXTERNAL_VENUE`
- `INSURANCE`
- `RECOVERY`

Hot wallets are operational liquidity only. Cold wallets hold excess backing and require stronger approval/signing controls. Phase 9 does not let ordinary application workers perform unrestricted cold-wallet signing.

Phase 8 withdrawal reserve controls remain the source for operational liquidity targets.
