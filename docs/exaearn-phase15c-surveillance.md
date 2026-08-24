# ExaEarn Phase 15C Surveillance

Phase 15C adds reviewable surveillance cases for market-maker behavior.

## Implemented Signal

- `RELATED_ACCOUNT_MARKET_OVERLAP`

This detects when related market-maker profiles under the same institution are assigned to the same market, creating a review case instead of automatically accusing or terminating the participant.

## Case Fields

- case UUID
- market maker
- institution
- signal type
- severity
- evidence
- status
- review metadata

Future phases can extend this with order-level spoofing, quote stuffing and wash-trade detections using the existing market surveillance infrastructure.
