# ExaEarn P2P Disputes

Existing `p2p_disputes` remains the dispute table.

Supported actions:

- Request more information.
- Resolve in buyer's favor by releasing escrow through canonical settlement.
- Resolve in seller's favor by releasing the reservation back to seller availability.

Admin resolution never directly edits balances. It calls the same escrow release/return services as normal order flows.
