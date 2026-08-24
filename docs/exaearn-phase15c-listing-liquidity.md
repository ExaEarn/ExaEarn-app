# ExaEarn Phase 15C Listing Liquidity

Listing liquidity is represented by market assignments and liquidity agreements.

## Market Assignment

An assignment links a market-maker profile to a market and stores obligations:

- minimum depth
- maximum spread
- minimum quote presence
- target quote size
- maximum inventory
- rebate profile
- optional Phase 15A listing liquidity requirement

## Liquidity Agreement

An agreement stores commercial and capital commitments for a market:

- base and quote asset commitment
- spread requirement
- depth requirement
- quote presence requirement
- rebate profile

## Readiness

`MarketMakerProgramService::listingReadiness()` marks a market ready only when at least one active assigned market maker has sufficient canonical ledger capital for the agreement.
