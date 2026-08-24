# ExaEarn P2P Payment Verification

Buyer clicking “I have paid” is not settlement.

## Current Modes

- Manual verification: supported.
- Payment evidence upload: supported and privately stored.
- Automated provider verification: architecture-ready, dependent on Phase 10 provider rails per market.
- Auto-release: disabled unless payment is strongly verified through integrated rails and policy permits it.

## Payment Evidence

Evidence records are stored in `p2p_payment_evidence` with:

- Evidence UUID.
- Trade.
- Uploader.
- Private storage path.
- MIME type.
- Size.
- SHA-256 file hash.
- Timestamp.

Screenshots and receipts help review, but do not by themselves release crypto.
