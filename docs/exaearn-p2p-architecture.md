# ExaEarn P2P Architecture

ExaEarn P2P is now structured as a controlled escrow marketplace.

```text
Advertisement
  -> Order creation
  -> P2P risk check
  -> Seller crypto reservation
  -> Buyer payment state
  -> Manual/provider payment confirmation
  -> Escrow settlement or return
  -> Reputation + reconciliation
```

## Current Domain Services

- `App\Domain\P2P\Services\P2PEscrowService`
- `App\Domain\P2P\Services\P2PFeeService`
- `App\Domain\P2P\Services\P2POrderEventService`
- `App\Domain\P2P\Services\P2PReputationService`
- `App\Domain\P2P\Services\P2PReconciliationService`
- `App\Domain\P2P\Services\P2PRiskEngine`
- `App\Domain\P2P\Services\P2POperationalReadinessService`

`P2PService` remains the compatibility facade for current web/mobile routes.

## Compatibility

Existing `/api/p2p/*` routes are retained. Phase 11-compatible aliases are available under `/api/v1/p2p/*`.

Admin visibility is available under `/api/admin/v1/p2p/*`.
