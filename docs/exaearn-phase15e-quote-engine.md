# ExaEarn Phase 15E Quote Engine

The quote engine calculates fair value from trusted ExaEarn market data, applies configurable spread and inventory adjustments, and emits bid/ask levels.

Outputs:

- bid/ask side
- price
- quantity
- level
- quote TTL
- fair-value snapshot
- inventory snapshot
- risk snapshot

Live mode submits generated Spot quotes through the existing Spot order path. Shadow mode records the plan only.
